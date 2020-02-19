<?php

namespace SM\Integrate\Data;

use SM\Core\Api\Data\Contract\ApiDataAbstract;

class XStorePickUpLocation extends ApiDataAbstract
{

    public function getId()
    {
        return $this->getData('entity_id');
    }

    public function getName()
    {
        return $this->getData('location_name');
    }

    public function getCode()
    {
        return $this->getData('location_code');
    }

    public function getEmail()
    {
        return $this->getData('email');
    }

    public function getAdress()
    {
        return $this->getData('address');
    }

    public function getTelephone()
    {
        return $this->getData('phone_number');
    }

    public function getCity()
    {
        return $this->getData('city');
    }

    public function getCountryId()
    {
        return $this->getData('country_id');
    }

    public function getRegion()
    {
        return $this->getData('region');
    }

    public function getPostcode()
    {
        return $this->getData('postcode');
    }

    public function getIsActive()
    {
        return $this->getData('is_active') == 1;
    }

    public function getStoreIds()
    {
        return $this->getData('store_ids');
    }
}
