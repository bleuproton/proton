<?php

namespace Marello\Bundle\InvoiceBundle\Controller;

use Marello\Bundle\InvoiceBundle\Entity\Invoice;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as Config;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class CreditmemoController extends Controller
{
    /**
     * @Config\Route("/", name="marello_invoice_creditmemo_index")
     * @Config\Template
     * @AclAncestor("marello_invoice_view")
     */
    public function indexAction()
    {
        return ['entity_class' => 'MarelloInvoiceBundle:Invoice'];
    }

    /**
     * @Config\Route("/view/{id}", requirements={"id"="\d+"}, name="marello_invoice_creditmemo_view")
     * @Config\Template
     * @AclAncestor("marello_invoice_view")
     *
     * @param Invoice $invoice
     *
     * @return array
     */
    public function viewAction(Invoice $invoice)
    {
        return ['entity' => $invoice];
    }
}
