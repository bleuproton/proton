<?php

namespace Marello\Bundle\NotificationBundle\Provider;

use Psr\Log\LoggerAwareTrait;

use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints\Email as EmailConstraints;

use Oro\Bundle\EmailBundle\Form\Model\Email;
use Oro\Bundle\EmailBundle\Mailer\Processor;
use Oro\Bundle\EmailBundle\Provider\EmailRenderer;
use Oro\Bundle\EmailBundle\Sender\EmailModelSender;
use Oro\Bundle\EmailBundle\Tools\EmailOriginHelper;
use Oro\Bundle\EmailBundle\Tools\EmailAddressHelper;
use Oro\Bundle\EmailBundle\Model\EmailHolderInterface;
use Oro\Bundle\NotificationBundle\Model\NotificationSettings;
use Oro\Bundle\EmailBundle\Tools\EmailAttachmentTransformer;

use Marello\Bundle\LocaleBundle\Manager\EmailTemplateManager;
use Marello\Bundle\NotificationBundle\Exception\MarelloNotificationException;

class EmailSendProcessor
{
    use LoggerAwareTrait;

    const OPTION_ATTACHMENTS = 'attachments';

    /** @var EmailConstraints $emailConstraint */
    protected $emailConstraint;

    /** @var EmailRenderer $renderer */
    protected $renderer;

    /** @var ValidatorInterface $validator */
    protected $validator;

    /** @var EmailOriginHelper $emailOriginHelper */
    protected $emailOriginHelper;

    /** @var EmailModelSender $emailModelSender */
    protected $emailModelSender;

    /** @var EmailAddressHelper $emailAddressHelper */
    protected $emailAddressHelper;

    /** @var EmailTemplateManager $emailTemplateManager */
    protected $emailTemplateManager;

    /** @var NotificationSettings $notificationSettings */
    private $notificationSettings;

    /** @var EmailAttachmentTransformer $emailAttachmentTransformer */
    protected $emailAttachmentTransformer;

    /**
     * AttachmentEmailSendProcessor constructor.
     * @param EmailModelSender $emailModelSender
     * @param EmailAddressHelper $emailAddressHelper
     * @param ValidatorInterface $validator
     * @param EmailOriginHelper $emailOriginHelper
     * @param EmailRenderer $renderer
     * @param EmailTemplateManager $emailTemplateManager
     * @param NotificationSettings $notificationSettings
     */
    public function __construct(
        EmailModelSender $emailModelSender,
        EmailAddressHelper $emailAddressHelper,
        ValidatorInterface $validator,
        EmailOriginHelper $emailOriginHelper,
        EmailRenderer $renderer,
        EmailTemplateManager $emailTemplateManager,
        NotificationSettings $notificationSettings,
        EmailAttachmentTransformer $emailAttachmentTransformer
    ) {
        $this->validator = $validator;
        $this->renderer = $renderer;
        $this->emailOriginHelper = $emailOriginHelper;
        $this->emailModelSender = $emailModelSender;
        $this->emailAddressHelper = $emailAddressHelper;
        $this->emailTemplateManager = $emailTemplateManager;
        $this->notificationSettings = $notificationSettings;
        $this->emailAttachmentTransformer = $emailAttachmentTransformer;
        $this->emailConstraint = new EmailConstraints(['message' => 'Invalid email address']);
    }

    /**
     * @param $templateName
     * @param array $recipients
     * @param $entity
     * @param array $data
     * @throws MarelloNotificationException
     * @throws \Twig\Error\Error
     */
    public function sendNotification($templateName, array $recipients, $entity, array $data = [])
    {
        $emailModel = new Email();
        $from = $this->getFormattedSender();
        $this->validateAddress($from);
        $emailModel->setFrom($from);
        $to = [];

        foreach ($recipients as $recipient) {
            if ($recipient) {
                $address = $this->getEmailAddress($recipient);
                $this->validateAddress($address);
                $to[] = $address;
            }
        }
        $emailModel->setTo($to);
        $template = $this->emailTemplateManager->findTemplate($templateName, $entity);
        /*
         * If template is not found, throw an exception.
         */
        if ($template === null) {
            throw new MarelloNotificationException(
                sprintf(
                    'Email template with name "%s" for entity "%s" was not found. Check if such template exists.',
                    $templateName,
                    \get_class($entity)
                )
            );
        }
        // set type earlier otherwise it will not render correctly as html...
        // see vendor/oro/platform/src/Oro/Bundle/EmailBundle/Mailer/Processor.php#438
        $emailModel->setType($template->getType());
        if ($this->emailTemplateManager->getLocalizedModel($template, $entity)) {
            $template = $this->emailTemplateManager->getLocalizedModel($template, $entity);
        }

        $templateData = $this->renderer->compileMessage($template, compact('entity'));
        list ($subjectRendered, $templateRendered) = $templateData;

        $emailModel->setSubject($subjectRendered);
        $emailModel->setBody($templateRendered);
        $this->addAttachments($emailModel, $data);
        // set order as context to show up in activity list
        $emailModel->setContexts([$entity]);
        $emailUser = null;
        try {
            $emailOrigin = $this->emailOriginHelper->getEmailOrigin(
                $emailModel->getFrom(),
                $emailModel->getOrganization()
            );
            $this->emailModelSender->send($emailModel, $emailModel->getOrigin());
        } catch (\Swift_SwiftException $exception) {
            $this->logger->error('Workflow send email template action.', ['exception' => $exception]);
        }

        $this->logger->info('Workflow send email template successful .', []);
    }

    /**
     * @param Email $emailModel
     * @param $data
     */
    protected function addAttachments(Email $emailModel, $data)
    {
        if (isset($data[self::OPTION_ATTACHMENTS]) || !empty($data[self::OPTION_ATTACHMENTS])) {
            $attachments = $data[self::OPTION_ATTACHMENTS];
            foreach ($attachments as $attachment) {
                $emailAttachmentEntity = $this->emailAttachmentTransformer->attachmentEntityToEntity($attachment);
                $emailAttachment = $this->emailAttachmentTransformer->attachmentEntityToModel($attachment);
                $emailAttachment->setEmailAttachment($emailAttachmentEntity);
                $emailModel->addAttachment($emailAttachment);
            }
        }
    }

    /**
     * @param string $email
     * @throws ValidatorException
     */
    protected function validateAddress($email): void
    {
        $errorList = $this->validator->validate($email, $this->emailConstraint);

        if ($errorList && $errorList->count() > 0) {
            throw new ValidatorException($errorList->get(0)->getMessage());
        }
    }

    /**
     * Get formatted From email address from Notification settings
     * @return string
     */
    private function getFormattedSender(): string
    {
        $sendFromSettings = $this->notificationSettings->getSender();
        list ($email, $name) = $sendFromSettings->toArray();
        return $this->emailAddressHelper->buildFullEmailAddress($email, $name);
    }

    /**
     * Get email address prepared for sending.
     *
     * @param mixed $recipient
     *
     * @return string
     */
    protected function getEmailAddress($recipient)
    {
        $name = null;
        if (is_string($recipient)) {
            $name = $email = $recipient;
        }

        if (is_object($recipient) && $recipient instanceof EmailHolderInterface) {
            $name = $email = $recipient->getEmail();
        }

        $emailAddress = $this->emailAddressHelper->extractPureEmailAddress($email);
        $name = $this->emailAddressHelper->extractEmailAddressName($name);

        return $this->emailAddressHelper->buildFullEmailAddress($emailAddress, $name);
    }
}
