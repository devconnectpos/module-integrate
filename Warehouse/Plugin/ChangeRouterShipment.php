<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 3/26/18
 * Time: 15:59
 */

namespace SM\Integrate\Warehouse\Plugin;

use BoostMyShop\AdvancedStock\Model\Router;
use SM\Integrate\Model\WarehouseIntegrateManagement;
use SM\Sales\Repositories\OrderManagement;

class ChangeRouterShipment
{

    /**
     * @param Router $subject
     * @param $result
     * @return null
     */
    public function afterGetWarehouseIdForOrderItem(
        Router $subject,
        $result
    ) {
        if (OrderManagement::$FROM_API && WarehouseIntegrateManagement::getWarehouseId()) {
            return WarehouseIntegrateManagement::getWarehouseId();
        }

        return $result;
    }
}
