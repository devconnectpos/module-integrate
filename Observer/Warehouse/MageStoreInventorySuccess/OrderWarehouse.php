<?php

namespace SM\Integrate\Observer\Warehouse\MageStoreInventorySuccess;

use Magento\Framework\Event\ObserverInterface;
use SM\Integrate\Model\WarehouseIntegrateManagement;

class OrderWarehouse implements ObserverInterface
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * OrderWarehouse constructor.
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magestore\InventorySuccess\Model\Warehouse $orderWarehouse */
        $orderWarehouse = $observer->getEvent()->getData('warehouse');

        if (\SM\Sales\Repositories\OrderManagement::$FROM_API
            && $orderWarehouse->getWarehouseId() != WarehouseIntegrateManagement::getWarehouseId()) {
            $warehouseModel = $this->objectManager->get(\Magestore\InventorySuccess\Model\Warehouse::class);
            $newOrderWarehouse = $warehouseModel->load(
                WarehouseIntegrateManagement::getWarehouseId()
            );
            $orderWarehouse->addData($newOrderWarehouse->getData());
        }
    }
}
