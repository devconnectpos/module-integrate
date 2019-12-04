<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 3/21/18
 * Time: 15:29
 */

namespace SM\Integrate\Warehouse;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\Core\Model\DataObject;
use SM\Integrate\Data\XWarehouse;
use SM\Integrate\Helper\Data as IntegrateHelper;
use SM\Integrate\Warehouse\Contract\AbstractWarehouseIntegrate;
use SM\Integrate\Warehouse\Contract\WarehouseIntegrateInterface;
use SM\Product\Repositories\ProductManagement\ProductStock;
use SM\XRetail\Helper\DataConfig;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;

class MagentoInventory100 extends AbstractWarehouseIntegrate implements WarehouseIntegrateInterface
{

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilderFactory
     */
    protected $searchCriteriaBuilderFactory;

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

    private $sourceItemProcessor;

    private $sourceItemRepository;

    /**
     * BootMyShop0015 constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface               $objectManager
     * @param IntegrateHelper                                         $integrateData
     * @param \Magento\Catalog\Api\ProductRepositoryInterface         $productRepository
     * @param \Magento\Framework\App\ResourceConnection               $resource
     * @param \SM\Product\Repositories\ProductManagement\ProductStock $productStock
     * @param \Magento\Store\Model\StoreManagerInterface              $storeManager
     * @param \Magento\Framework\Api\SearchCriteriaBuilderFactory     $searchCriteriaBuilderFactory
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        IntegrateHelper $integrateData,
        ProductRepositoryInterface $productRepository,
        ResourceConnection $resource,
        ProductStock $productStock,
        StoreManagerInterface $storeManager,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
    ) {
        $this->productStock = $productStock;
        $this->resource     = $resource;
        $this->connection   = $resource->getConnection();
        $this->storeManager = $storeManager;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        parent::__construct($objectManager, $integrateData, $productRepository);
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
                           "is_in_stock"        => "status"
                       ]
                   )
                   ->where(
                       "warehouse_item.source_code = '{$warehouseId}' 
                       OR (e.type_id <> 'simple' AND e.type_id <> 'virtual')"
                   );
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

    /**
     * @return mixed
     */
    protected function getStockSourceLinkCollection()
    {
        return $this->objectManager->create('\Magento\Inventory\Model\ResourceModel\StockSourceLink\Collection');
    }

    /**
     * @return mixed
     */
    protected function getWebsiteCollection()
    {
        return $this->objectManager->create('\Magento\Store\Model\ResourceModel\Website\Collection');
    }

    /**
     * @return mixed
     */
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

    /**
     * @param $product
     * @param $warehouseId
     * @param $item
     *
     * @return array|mixed
     */
    public function getStockItem($product, $warehouseId, $item)
    {
        $defaultStock                = $this->productStock->getStock($product, 0);
        $defaultStock['qty']         = $item->getData('available_quantity');
        $listType = ['simple', 'virtual', 'giftcard', 'aw_giftcard', 'aw_giftcard2'];
        if (in_array($item->getData('type_id'), $listType)) {
            if ($item->getData('available_quantity') > 0 && $item->getData('is_in_stock') == 1) {
                $defaultStock['is_in_stock'] = 1;
            } else {
                $defaultStock['is_in_stock'] = 0;
            }
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

    /**
     * @return mixed
     */
    public function getStockId()
    {
        $websiteCode = $this->storeManager->getWebsite()->getCode();
        return $this->objectManager->get(\Magento\InventorySalesApi\Api\StockResolverInterface::class)->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode)->getStockId();
    }

    /**
     * @param $product
     *
     * @return bool
     */
    public function isProductSalable($product)
    {
        $stockId = $this->getStockId();
        $stockItemToCheck = $product['stock_item_to_check'];
        $isSalable = true;
        foreach ($stockItemToCheck as $key  => $value) {
            $childProduct = $this->productRepository->getById($value);
            $isSalableItem = $this->objectManager->create(
                \Magento\InventorySalesApi\Api\IsProductSalableForRequestedQtyInterface::class
            )->execute(
                $childProduct->getSku(),
                $stockId,
                $product['qty_ordered']
            )->isSalable();
            if (!$isSalableItem) {
                $isSalable = false;
            }
        }
        return $isSalable;
    }

    /**
     * @param $stockSourceLink
     *
     * @return array
     */
    public function getListStock($stockSourceLink)
    {
        $listStock = [];
        foreach ($stockSourceLink->getItems() as $s) {
            $stock     = $this->getStockCollection()->get($s['stock_id']);
            $extension = $stock->getData('extension_attributes');
            foreach ($extension->getSalesChannels() as $sales_channel) {
                $websites = $this->getWebsiteCollection()->addFieldToFilter(
                    'code',
                    $sales_channel->getData('code')
                );
                $channel  = $sales_channel->getData();
                foreach ($websites->getItems() as $w) {
                    $channel['website_id'] = $w->getId();
                }
            }
        }
    }

    /**
     * @param \SM\RefundWithoutReceipt\Model\RefundWithoutReceiptItem        $item
     * @param \SM\RefundWithoutReceipt\Model\RefundWithoutReceiptTransaction $transaction
     *
     * @return mixed|void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function returnItemToStock($item, $transaction)
    {
        $sku            = $item->getProductSku();
        $sourceCode     = $transaction->getWarehouseId();
        $source         = $this->getWarehouseCollection(new DataObject(['entity_id' => $sourceCode]))
                               ->getFirstItem();
        $sourceItems    = [];
        $sourceItemsMap = $this->getCurrentSourceItemsMap($sku);
        foreach ($sourceItemsMap as $sourceItem) {
            $sourceItemData = [
                'source_code'                  => $sourceItem->getSourceCode(),
                'quantity'                     => (float)$sourceItem->getQuantity(),
                'status'                       => $sourceItem->getStatus(),
                'name'                         => $source->getData('name'),
                'source_status'                => 'true',
                'notify_stock_qty'             => '1',
                'notify_stock_qty_use_default' => '1',
                'initialize'                   => 'true',
                'record_id'                    => $sourceItem->getSourceCode()
            ];
            if ($sourceItem->getSourceCode() === $sourceCode) {
                $sourceItemData['quantity'] = (float)$sourceItem->getQuantity() + (float)$item->getProductQty();
            }

            $sourceItems[] = $sourceItemData;
        }
        $this->getSourceItemProcessor()->process($sku, $sourceItems);
        $this->triggerRealTimeProduct($item->getProductId());
    }

    /**
     * @return mixed
     */
    protected function getSourceItemProcessor()
    {
        if ($this->sourceItemProcessor === null) {
            $this->sourceItemProcessor = $this->objectManager->create('Magento\InventoryCatalogAdminUi\Observer\SourceItemsProcessor');
        }

        return $this->sourceItemProcessor;
    }

    /**
     * @return mixed
     */
    protected function getSourceItemRepository()
    {
        if ($this->sourceItemRepository === null) {
            $this->sourceItemRepository = $this->objectManager->create('Magento\InventoryApi\Api\SourceItemRepositoryInterface');
        }

        return $this->sourceItemRepository;
    }

    /**
     * @param $sku
     *
     * @return array
     */
    protected function getCurrentSourceItemsMap($sku)
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteria        = $searchCriteriaBuilder->addFilter(ProductInterface::SKU, $sku)->create();
        $sourceItems           = $this->getSourceItemRepository()->getList($searchCriteria)->getItems();

        $sourceItemMap = [];
        if ($sourceItems) {
            foreach ($sourceItems as $sourceItem) {
                $sourceItemMap[$sourceItem->getSourceCode()] = $sourceItem;
            }
        }

        return $sourceItemMap;
    }
}
