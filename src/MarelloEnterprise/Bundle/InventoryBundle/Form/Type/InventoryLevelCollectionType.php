<?php

namespace Marello\Bundle\InventoryBundle\Form\Type;

use Oro\Bundle\FormBundle\Form\Type\CollectionType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InventoryLevelCollectionType extends AbstractType
{
    const NAME = 'marello_inventory_inventorylevel_collection';

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'type'                 => InventoryLevelType::NAME,
            'show_form_when_empty' => false,
            'error_bubbling'       => false,
            'cascade_validation'   => true,
            'prototype_name'       => '__nameinventorylevelcollection__',
            'prototype'            => true,
            'handle_primary'       => false,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return CollectionType::NAME;
    }
}
