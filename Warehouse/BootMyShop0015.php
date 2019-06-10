<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 3/21/18
 * Time: 15:29
 */

namespace SM\Integrate\Warehouse;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\Core\Api\SearchResult;
use SM\Core\Model\DataObject;
use SM\Integrate\Data\XWarehouse;
use SM\Integrate\Helper\Data;
use SM\Integrate\Warehouse\Contract\AbstractWarehouseIntegrate;
use SM\Integrate\Warehouse\Contract\WarehouseIntegrateInterface;
use SM\Product\Repositories\ProductManagement\ProductStock;
use SM\XRetail\Helper\DataConfig;

class BootMyShop0015 extends AbstractWarehouseIntegrate implements WarehouseIntegrateInterface
{

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resource;
    /**
     * @var \SM\Product\Repositories\ProductManagement\ProductStock
     */
    private $productStock;

    protected $transformWarehouseData
        = [
            "warehouse_id"   => "w_id",
            "warehouse_name" => "w_name",
            "warehouse_code" => "warehouse_code",
            "contact_email"  => "w_email",
            "telephone"      => "w_telephone",
            "fax"            => "w_fax",
            "city"           => "w_city",
            "country_id"     => "w_country",
            "region"         => "w_state",
            "region_id"      => "region_id",
            "is_active"      => "w_is_active",
            "is_primary"     => "w_is_primary",
            "company"        => "w_company_name",
            "street1"        => "w_street1",
            "street2"        => "w_street2",
        ];
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * BootMyShop0015 constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface               $objectManager
     * @param \SM\Integrate\Helper\Data                               $integrateData
     * @param \Magento\Framework\App\ResourceConnection               $resource
     * @param \SM\Product\Repositories\ProductManagement\ProductStock $productStock
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Data $integrateData,
        ResourceConnection $resource,
        ProductStock $productStock,
        StoreManagerInterface $storeManager
    ) {
        $this->productStock = $productStock;
        $this->resource     = $resource;
        $this->storeManager = $storeManager;
        parent::__construct($objectManager, $integrateData);
    }

    /**
     * @param \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection $collection
     * @param int                                                                     $warehouseId
     * @param                                                                         $searchCriteria
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    public function filterProductCollectionByWarehouse($collection, $warehouseId)
    {
        $collection->getSelect()
                   ->joinLeft(
                       ['warehouse_item' => $this->resource->getTableName("bms_advancedstock_warehouse_item")],
                       "warehouse_item.wi_warehouse_id = {$warehouseId} AND warehouse_item.wi_product_id = e.entity_id",
                       [
                           "physical_quantity"  => "wi_physical_quantity",
                           "available_quantity" => "wi_available_quantity"
                       ]
                   )
                   ->where("(e.type_id = 'simple'" . " AND " . "warehouse_item.wi_available_quantity > 0) OR e.type_id <> 'simple'");

        return $collection;
    }

    /**
     * @param $warehouseId
     *
     * @return array
     */
    public function getListProductByWarehouse($warehouseId)
    {
        return [];
    }

    /**
     * @param $searchCriteria
     *
     * @return \SM\Core\Api\SearchResult
     */
    public function loadWarehouseData($searchCriteria)
    {
        // TODO: Implement loadWarehouseData() method.
        $searchResult   = new SearchResult();
        $items          = [];
        $size           = 0;
        $lastPageNumber = 0;
        if ($this->integrateData->isIntegrateWH()) {
            $warehouseCollection = $this->getWarehouseCollection($searchCriteria);
            $size                = $warehouseCollection->getSize();
            $lastPageNumber      = $warehouseCollection->getLastPageNumber();

            if ($warehouseCollection->getLastPageNumber() < $searchCriteria->getData('currentPage')) {
            } else {
                foreach ($warehouseCollection as $item) {
                    $_data = new XWarehouse();

                    $warehouseRouting = $this->getRoutingStoreWarehouseCollection()
                                             ->addFieldToFilter('rsw_warehouse_id', $item->getData('w_id'))
                                             ->toArray();

                    foreach ($this->transformWarehouseData as $k => $v) {
                        $_data->setData($k, $item->getData($v));
                    }

                    $_data['addition_data'] = $warehouseRouting;
                    array_push($items, $_data);
                }
            }
        }

        return $searchResult
            ->setItems($items)
            ->setTotalCount($size)
            ->setLastPageNumber($lastPageNumber);
    }

    /**
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    protected function getRoutingStoreWarehouseCollection()
    {
        return $this->objectManager->create(
            'BoostMyShop\AdvancedStock\Model\ResourceModel\Routing\Store\Warehouse\Collection'
        );
    }

    /**
     * @param $searchCriteria
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    public function getWarehouseCollection($searchCriteria)
    {
        $collection = $this->objectManager->create(
            'BoostMyShop\AdvancedStock\Model\ResourceModel\Warehouse\Collection'
        );

        if (is_nan($searchCriteria->getData('currentPage'))) {
            $collection->setCurPage(1);
        } else {
            $collection->setCurPage($searchCriteria->getData('currentPage'));
        }
        if (is_nan($searchCriteria->getData('pageSize'))) {
            $collection->setPageSize(
                DataConfig::PAGE_SIZE_LOAD_DATA
            );
        } else {
            $collection->setPageSize(
                $searchCriteria->getData('pageSize')
            );
        }

        if ($searchCriteria->getData('entity_id') || $searchCriteria->getData('entityId')) {
            if (is_null($searchCriteria->getData('entity_id'))) {
                $ids = $searchCriteria->getData('entityId');
            } else {
                $ids = $searchCriteria->getData('entity_id');
            }
            $collection->addFieldToFilter('w_id', ['in' => explode(",", $ids)]);
        }

        return $collection;
    }

    public function getStockItem($product, $warehouseId, $item)
    {
        $defaultStock = $this->productStock->getStock($product, 0);

        $warehouseStockItem = $item->getData('available_quantity');

        if ($warehouseStockItem) {
            $defaultStock['qty'] = $warehouseStockItem;
        }

        return $defaultStock;
    }


    /**
     * @param $searchCriteria
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    public function getWarehouseItemCollection($searchCriteria)
    {
        $collection = $this->objectManager->create(
            'BoostMyShop\AdvancedStock\Model\ResourceModel\Warehouse\Item\Collection'
        );

        if (is_nan($searchCriteria->getData('currentPage'))) {
            $collection->setCurPage(1);
        } else {
            $collection->setCurPage($searchCriteria->getData('currentPage'));
        }
        if (is_nan($searchCriteria->getData('pageSize'))) {
            $collection->setPageSize(
                DataConfig::PAGE_SIZE_LOAD_DATA
            );
        } else {
            $collection->setPageSize(
                $searchCriteria->getData('pageSize')
            );
        }

        if ($searchCriteria->getData('entity_id') || $searchCriteria->getData('entityId')) {
            if (is_null($searchCriteria->getData('entity_id'))) {
                $ids = $searchCriteria->getData('entityId');
            } else {
                $ids = $searchCriteria->getData('entity_id');
            }
            $collection->addFieldToFilter('wi_product_id', ['in' => explode(",", $ids)]);
        }

        if ($searchCriteria->getData('warehouse_id')) {
            $collection->addFieldToFilter('wi_warehouse_id', $searchCriteria->getData('warehouse_id'));
        }
        if ($searchCriteria->getData('wi_product_id')) {
            $collection->addFieldToFilter('wi_product_id', $searchCriteria->getData('wi_product_id'));
        }

        return $collection;
    }

    /**
     * @param int $productId
     * @param int $warehouseId
     *
     * @return array
     */
    public function getWarehouseStockItem($productId, $warehouseId)
    {
        $whItem = $this->getWarehouseItemCollection(
            new DataObject(
                [
                    "entity_id"    => $productId,
                    "warehouse_id" => $warehouseId
                ]
            )
        )->getFirstItem();
        $product = $this->objectManager->create('Magento\Catalog\Model\Product')->load($productId);
        $defaultStock = $this->productStock->getStock($product, 0);
        if ($whItem->getData('wi_id')) {
            return [
                'physical_quantity'  => $whItem->getData("wi_physical_quantity"),
                'available_quantity' => $whItem->getData("wi_available_quantity"),
                'is_qty_decimal'     => $defaultStock["is_qty_decimal"],
            ];
        } else {
            return [];
        }
    }

    public function isProductSalable($product)
    {
        return true;
    }
}
