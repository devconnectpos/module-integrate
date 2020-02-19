<?php

namespace SM\Integrate\Data;

use SM\Core\Api\Data\Contract\ApiDataAbstract;

class XCustomFields extends ApiDataAbstract
{

    public function getId()
    {
        return $this->getData('id');
    }

    public function getName()
    {
        return $this->getData('name');
    }

    public function getType()
    {
        return $this->getData('type');
    }

    public function getRefers()
    {
        return $this->getData('refers');
    }

    public function getWebsiteIds()
    {
        return $this->getData('website_ids');
    }

    public function getVisibleStatusIds()
    {
        return $this->getData('visible_for_status_ids');
    }

    public function getIsRequired()
    {
        return $this->getData('is_required') == 1;
    }

    public function getIsDisplayInLabel()
    {
        return $this->getData('is_display_in_label') == 1;
    }

    public function getOptions()
    {
        return $this->getData('options');
    }

    public function getFrontendLabels()
    {
        return $this->getData('frontend_labels');
    }

    public function getIsActive()
    {
        return $this->getData('is_active') == 1;
    }

    public function getStorefrontLabel()
    {
        return $this->getData('storefront_label');
    }
}
