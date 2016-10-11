<?php

namespace Marello\Bundle\ShippingBundle\Integration;

use Marello\Bundle\AddressBundle\Entity\MarelloAddress;
use Marello\Bundle\ShippingBundle\Entity\Shipment;

interface ShippingServiceDataProviderInterface
{
    /**
     * @return MarelloAddress | null
     */
    public function getShippingShipTo();

    /**
     * @return MarelloAddress | null
     */
    public function getShippingShipFrom();

    /**
     * @return string
     */
    public function getShippingCustomerEmail();

    /**
     * @return string
     */
    public function getShippingWeight();

    /**
     * @return string
     */
    public function getShippingDescription();

    /**
     * @param $entity
     * @return ShippingServiceDataProviderInterface
     */
    public function setEntity($entity);

    public function getEntity();
}