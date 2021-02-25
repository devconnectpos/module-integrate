<?php

namespace SM\Integrate\Warehouse\Rewrite\Product;

use Magento\Framework\App\ObjectManager;

class Collection extends \Magento\Catalog\Model\ResourceModel\Product\Collection
{
    
    public function addItem(\Magento\Framework\DataObject $object)
    {
        $integrateHelper = ObjectManager::getInstance()->get(
            \SM\Integrate\Helper\Data::class
        );
        if ($integrateHelper->isMagestoreInventory()) {
            $itemId = $this->_getItemId($object);
    
            if ($itemId !== null) {
                $this->_items[$itemId] = $object;
            } else {
                $this->_addItem($object);
            }
            return $this;
        }
        return parent::addItem($object);
    }
}
