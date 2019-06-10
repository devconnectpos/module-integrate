<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 3/26/18
 * Time: 16:57
 */

namespace SM\Integrate\Warehouse\Plugin;

use BoostMyShop\OrderPreparation\Observer\SalesOrderShipmentSaveAfter;
use Closure;
use Magento\Framework\Event\Observer;
use SM\Integrate\Model\WarehouseIntegrateManagement;
use SM\Sales\Repositories\OrderManagement;
use SM\Sales\Repositories\ShipmentManagement;

class ForceShipmentProcessing
{

    /**
     * @param \BoostMyShop\OrderPreparation\Observer\SalesOrderShipmentSaveAfter $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Event\Observer $observer
     * @return mixed|void
     */
    public function aroundExecute(
        SalesOrderShipmentSaveAfter $subject,
        Closure $proceed,
        Observer $observer
    ) {
        if ((OrderManagement::$FROM_API && WarehouseIntegrateManagement::getWarehouseId())
            || ShipmentManagement::$FROM_API) {
            return;
        } else {
            return $proceed($observer);
        }
    }
}
