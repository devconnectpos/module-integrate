<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 3/21/18
 * Time: 15:29
 */

namespace SM\Integrate\Warehouse;

use SM\Core\Model\DataObject;
use SM\Integrate\Data\XWarehouse;
use SM\Integrate\Warehouse\Contract\AbstractWarehouseIntegrate;
use SM\Integrate\Warehouse\Contract\WarehouseIntegrateInterface;
use SM\XRetail\Helper\DataConfig;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;

class MagentoInventory100 extends AbstractWarehouseIntegrate implements WarehouseIntegrateInterface
{

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resource;
    /**
     * @var \SM\Product\Repositories\ProductManagement\ProductStock
     */
    private $productStock;

    protected $_transformWarehouseData
        = [
            "warehouse_id"   => "source_code",
            "warehouse_name" => "name",
            "warehouse_code" => "source_code",
            "contact_email"  => "email",
            "telephone"      => "telephone",
            "fax"            => "fax",
            "city"           => "city",
            "country_id"     => "country_id",
            "region"         => "region",
            "region_id"      => "region_id",
            "is_active"      => "enabled",
            "is_primary"     => "is_primary",
            "company"        => "company",
            "street1"        => "street",
            "street2"        => "street2",
        ];
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $connection;

    /**
     * BootMyShop0015 constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface               $objectManager
     * @param \SM\Integrate\Helper\Data                               $integrateData
     * @param \Magento\Framework\App\ResourceConnection               $resource
     * @param \SM\Product\Repositories\ProductManagement\ProductStock $productStock
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \SM\Integrate\Helper\Data $integrateData,
        \Magento\Framework\App\ResourceConnection $resource,
        \SM\Product\Repositories\ProductManagement\ProductStock $productStock,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->productStock = $productStock;
        $this->resource = $resource;
        $this->connection = $resource->getConnection();

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
                       ['warehouse_item' => $this->resource->getTableName("inventory_source_item")],
                       "warehouse_item.source_code = '{$warehouseId}' AND warehouse_item.sku = e.sku",
                       [
                           "physical_quantity"  => "quantity",
                           "available_quantity" => "quantity",
                           "is_in_stock" => "status"
                       ])
                   ->where("warehouse_item.source_code = '{$warehouseId}' OR (e.type_id <> 'simple' AND e.type_id <> 'virtual')");
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
        $searchResult   = new \SM\Core\Api\SearchResult();
        $items          = [];
        $size           = 0;
        $lastPageNumber = 0;
        if ($this->integrateData->isMagentoInventory()) {
            $warehouseCollection = $this->getWarehouseCollection($searchCriteria);
            $size                = $warehouseCollection->getSize();
            $lastPageNumber      = $warehouseCollection->getLastPageNumber();

            if ($warehouseCollection->getLastPageNumber() < $searchCriteria->getData('currentPage')) {

            }
            else {
                foreach ($warehouseCollection as $item) {
                    $_data = new XWarehouse();
                    $store            = $this->storeManager->getStore($searchCriteria->getData('storeId'));
                    $stockSourceLink = $this->getStockSourceLinkCollection()->addFieldToFilter('source_code', $item->getData('source_code'));
                    $listStock = [];
                    foreach ($stockSourceLink->getItems() as $s) {
                        $stock = $this->getStockCollection()->get($s['stock_id']);
                        $extension = $stock->getData('extension_attributes');
                        foreach ($extension->getSalesChannels() as $sales_channel) {
                            $websites = $this->getWebsiteCollection()->addFieldToFilter('code', $sales_channel->getData('code'));
                            $channel = $sales_channel->getData();
                            foreach ($websites->getItems() as $w) {
                                $channel['website_id'] = $w->getId();
                            }

                            $listStock[] = $channel;
                        }
                    }

                    foreach ($this->_transformWarehouseData as $k => $v) {
                        $_data->setData($k, $item->getData($v));
                    }

                    $_data['addition_data'] = $listStock;
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
        return $this->objectManager->create('BoostMyShop\AdvancedStock\Model\ResourceModel\Routing\Store\Warehouse\Collection');
    }

    protected function getStockSourceLinkCollection()
    {
        return $this->objectManager->create('\Magento\Inventory\Model\ResourceModel\StockSourceLink\Collection');
    }

    protected function getWebsiteCollection()
    {
        return $this->objectManager->create('\Magento\Store\Model\ResourceModel\Website\Collection');
    }

    protected function getStockCollection()
    {
        return $this->objectManager->create('Magento\InventoryApi\Api\StockRepositoryInterface');
    }

    /**
     * @param $searchCriteria
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    public function getWarehouseCollection($searchCriteria)
    {
        $collection = $this->objectManager->create('\Magento\Inventory\Model\ResourceModel\Source\Collection');

        $collection->setCurPage(is_nan($searchCriteria->getData('currentPage')) ? 1 : $searchCriteria->getData('currentPage'));
        $collection->setPageSize(
            is_nan($searchCriteria->getData('pageSize')) ? DataConfig::PAGE_SIZE_LOAD_DATA : $searchCriteria->getData('pageSize')
        );

        if ($searchCriteria->getData('entity_id') || $searchCriteria->getData('entityId')) {
            $ids = is_null($searchCriteria->getData('entity_id')) ? $searchCriteria->getData('entityId') : $searchCriteria->getData('entity_id');
            $collection->addFieldToFilter('source_code', ['in' => explode(",", $ids)]);
        }

        return $collection;
    }

    public function getStockItem($product, $warehouseId, $item)
    {
        $defaultStock = $this->productStock->getStock($product, 0);
        $defaultStock['qty'] =  $item->getData('available_quantity');
        $defaultStock['is_in_stock'] = $item->getData('is_in_stock');
        return $defaultStock;
    }


    /**
     * @param $searchCriteria
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    public function getWarehouseItemCollection($searchCriteria)
    {
        $collection = $this->objectManager->create('\Magento\Inventory\Model\ResourceModel\SourceItem\Collection');
        $collection->setCurPage(is_nan($searchCriteria->getData('currentPage')) ? 1 : $searchCriteria->getData('currentPage'));
        $collection->setPageSize(
            is_nan($searchCriteria->getData('pageSize')) ? DataConfig::PAGE_SIZE_LOAD_DATA : $searchCriteria->getData('pageSize')
        );

        if ($searchCriteria->getData('warehouse_id')) {
            $collection->addFieldToFilter('source_code', $searchCriteria->getData('warehouse_id'));
        }
        if ($searchCriteria->getData('entity_sku') || $searchCriteria->getData('entitySku')) {
            $skus = is_null($searchCriteria->getData('entity_sku')) ? $searchCriteria->getData('entitySku') : $searchCriteria->getData('entity_sku');
            $collection->addFieldToFilter('sku', ['in' => explode(",", $skus)]);
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
        $product = $this->objectManager->create('Magento\Catalog\Model\Product')->load($productId);
        $defaultStock = $this->productStock->getStock($product, 0);
        $whItem = $this->getWarehouseItemCollection(
            new DataObject(
                [
                    "entity_sku"    => $product->getSku(),
                    "warehouse_id" => $warehouseId
                ]))->getFirstItem();
        if ($whItem->getData('source_item_id')) {
            return [
                'physical_quantity'  => $whItem->getData("quantity"),
                'available_quantity' => $whItem->getData("quantity"),
                'is_qty_decimal'     => $defaultStock["is_qty_decimal"],
            ];
        }
        else {
            return [];
        }
    }

    public function getStockId()
    {
        $websiteCode = $this->storeManager->getWebsite()->getCode();
        return $this->objectManager->get(\Magento\InventorySalesApi\Api\StockResolverInterface::class)->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode)->getStockId();
    }

    public function isProductSalable($product)
    {
        $stockId = $this->getStockId();
        return $this->objectManager->create('\Magento\InventorySalesApi\Api\IsProductSalableForRequestedQtyInterface')->execute($product['sku'], $stockId, $product['qty_ordered'])->isSalable();

    }
}
