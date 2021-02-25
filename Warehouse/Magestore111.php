<?php
/**
 * Created by IntelliJ IDEA.
 * User: vjcspy
 * Date: 4/10/17
 * Time: 11:36 AM
 */

namespace SM\Integrate\Warehouse;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;
use Magento\CatalogInventory\Model\StockManagement;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use SM\Core\Api\SearchResultFactory;
use SM\Integrate\Data\XWarehouseFactory;
use SM\Integrate\Helper\Data as IntegrateHelper;
use SM\Integrate\Warehouse\Contract\AbstractWarehouseIntegrate;
use SM\Integrate\Warehouse\Contract\WarehouseIntegrateInterface;
use SM\Product\Repositories\ProductManagement\ProductStock;
use SM\XRetail\Helper\DataConfig;

class Magestore111 extends AbstractWarehouseIntegrate implements WarehouseIntegrateInterface
{
    /**
     * @var \SM\Product\Repositories\ProductManagement\ProductStock
     */
    private $productStock;

    /**
     * @var SearchResultFactory
     */
    private $searchResultFactory;

    /**
     * @var XWarehouseFactory
     */
    private $xWarehouseFactory;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilderFactory
     */
    protected $searchCriteriaBuilderFactory;

    /**
     * @var StockConfigurationInterface
     */
    protected $catalogInventoryConfiguration;

    /**
     * @var StockRegistryProviderInterface
     */
    protected $stockRegistryProvider;

    /**
     * @var StockManagement
     */
    protected $stockMangement;

    /**
     * Magestore111 constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface               $objectManager
     * @param \SM\Integrate\Helper\Data                               $integrateData
     * @param \Magento\Catalog\Api\ProductRepositoryInterface         $productRepository
     * @param \SM\Product\Repositories\ProductManagement\ProductStock $productStock
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        IntegrateHelper $integrateData,
        ProductRepositoryInterface $productRepository,
        ProductStock $productStock,
        SearchResultFactory $searchResultFactory,
        XWarehouseFactory $xWarehouseFactory,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        StockConfigurationInterface $catalogInventoryConfiguration,
        StockRegistryProviderInterface $stockRegistryProvider,
        StockManagement $stockManagement
    ) {
        $this->productStock = $productStock;
        $this->searchResultFactory = $searchResultFactory;
        $this->xWarehouseFactory = $xWarehouseFactory;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->catalogInventoryConfiguration = $catalogInventoryConfiguration;
        $this->stockRegistryProvider = $stockRegistryProvider;
        $this->stockMangement = $stockManagement;
        parent::__construct($objectManager, $integrateData, $productRepository);
    }

    /**
     * @param $warehouseId
     *
     * @return array
     */
    public function getListProductByWarehouse($warehouseId)
    {
        $result = $this->getWarehouseManagement()->getListProduct($warehouseId);
        $productIds = [];

        if (count($result) > 0) {
            foreach ($result as $item) {
                $productIds[$item->getProductId()] = $item->getProductId();
            }
        }

        return $productIds;
    }

    /**
     * @param $collection
     * @param $warehouseId
     * @param $searchCriteria
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    public function filterProductCollectionByWarehouse($collection, $warehouseId)
    {
        return $collection->addFieldToFilter('entity_id', ['in' => $this->getListProductByWarehouse($warehouseId)]);
    }

    /**
     * @param DataObject $searchCriteria
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    public function getWarehouseCollection($searchCriteria)
    {
        /** @var \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection $collection */
        $collection = $this->objectManager->create(
            'Magestore\InventorySuccess\Model\ResourceModel\Warehouse\Collection'
        );
        if ($searchCriteria->getData('entity_id')) {
            $collection->addFieldToFilter('warehouse_id', $searchCriteria->getData('entity_id'));
        }

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

        return $collection;
    }

    /**
     * @param      $product
     * @param      $warehouseId
     * @param null $scopeId
     *
     * @return array|mixed|null
     */
    public function getStockItem($product, $warehouseId, $scopeId = null)
    {
        $websiteId = $warehouseId ? $warehouseId : $scopeId;

        return $this->getStock($websiteId, $product->getId());
    }

    /**
     * @param $searchCriteria
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    public function getWarehouseItemCollection($searchCriteria)
    {
        /** @var $collection \Magestore\InventorySuccess\Model\ResourceModel\Warehouse\Product\Collection */
        $collection = $this->getWarehouseProductCollection();
        $collection->getSelect()->joinInner(
            ['catalog_product' => $collection->getTable('catalog_product_entity')],
            'main_table.product_id = catalog_product.entity_id',
            ['product_sku' => 'catalog_product.sku']
        )->joinInner(
            ['warehouse' => $collection->getTable('os_warehouse')],
            'main_table.website_id = warehouse.warehouse_id',
            ['warehouse_id', 'warehouse_code', 'warehouse_name']
        );

        //Add filters from root filters group to the collection
        foreach ($searchCriteria->getFilterGroups() as $group) {
            $this->addFilterGroupToCollection($group, $collection);
        }

        /** @var \Magento\Framework\Api\SortOrder $sortOrder */
        foreach ((array)$searchCriteria->getSortOrders() as $sortOrder) {
            $field = $sortOrder->getField();
            $collection->addOrder(
                $field,
                ($sortOrder->getDirection() == \Magento\Framework\Api\SortOrder::SORT_ASC) ? 'ASC' : 'DESC'
            );
        }

        $collection->setCurPage($searchCriteria->getCurrentPage());
        $collection->setPageSize($searchCriteria->getPageSize());
        $collection->load();

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
        /** @var \Magestore\InventorySuccess\Model\Warehouse $warehouseModel */
        $warehouseModel = $this->objectManager->get('Magestore\InventorySuccess\Model\Warehouse');
        $warehouse = $warehouseModel->load($warehouseId);

        if ($warehouse->getId()) {
            $stock = $this->getStock($warehouse->getId(), $productId);
            $qtyToShip = isset($stock['qty_to_ship']) ? $stock['qty_to_ship'] : 0;

            return [
                'physical_quantity'  => isset($stock['total_qty']) ? $stock['total_qty'] : "0",
                'available_quantity' => isset($stock['total_qty']) ? (string)($stock['total_qty'] - $qtyToShip) : "0",
                'is_qty_decimal'     => isset($stock['is_qty_decimal']) ? $stock['is_qty_decimal'] : "0",
            ];
        }

        return [];
    }

    public function isProductSalable($product)
    {
        return true;
    }

    /**
     * @param \SM\RefundWithoutReceipt\Model\RefundWithoutReceiptItem        $item
     * @param \SM\RefundWithoutReceipt\Model\RefundWithoutReceiptTransaction $transaction
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function returnItemToStock($item, $transaction)
    {
        if(!$this->canProcessItem($item)){
            return;
        }

        $this->stockMangement->backItemQty($item->getProductId(), $item->getProductQty(), $transaction->getWarehouseId());
        $this->triggerRealTimeProduct($item->getProductId());
    }

    /**
     * @param $searchCriteria
     *
     * @return \SM\Core\Api\SearchResult
     */
    public function loadWarehouseData($searchCriteria)
    {
        $searchResult = $this->searchResultFactory->create();
        $items = [];
        $size = 0;
        $lastPageNumber = 0;

        if ($this->integrateData->isMagestoreInventory()) {
            $warehouseCollection = $this->getWarehouseCollection($searchCriteria);
            $size = $warehouseCollection->getSize();
            $lastPageNumber = $warehouseCollection->getLastPageNumber();

            if ($warehouseCollection->getLastPageNumber() >= $searchCriteria->getData('currentPage')) {
                foreach ($warehouseCollection as $item) {
                    $_data = $this->xWarehouseFactory->create();

                    foreach ($this->transformWarehouseData as $k => $v) {
                        $_data->setData($k, $item->getData($v));
                    }
    
                    $_data['addition_data'] = ['type' => 'mage_store'];
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
     * Manage stock of product in this item or not
     *
     * @param \SM\RefundWithoutReceipt\Model\RefundWithoutReceiptItem $item
     * @return bool
     */
    protected function isManageStock($item)
    {
        /* do not manage qty of this product type */
        $productType = $item->getProductType();

        /**
         * Mark add
         * khi add sp con cua? grouped trong backend va frontend
         * Magento luu product type la grouped nen khong tru available qty
         */
        if ($productType == \Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE){
            $productType = \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE;
        }

        if(!$this->catalogInventoryConfiguration->isQty($productType)){
            return false;
        }

        $scopeId = $this->catalogInventoryConfiguration->getDefaultScopeId();
        $stockItem = $this->stockRegistryProvider->getStockItem($item->getProductId(), $scopeId);

        /* do not manage stock of this product */
        if(!$stockItem->getUseConfigManageStock() && !$stockItem->getManageStock()) {
            return false;
        }
        if($stockItem->getUseConfigManageStock() && !$this->catalogInventoryConfiguration->getManageStock()) {
            return false;
        }

        return true;
    }

    /**
     * @param \SM\RefundWithoutReceipt\Model\RefundWithoutReceiptItem $item
     *
     * @return bool
     */
    protected function canProcessItem($item)
    {
        /* check manage stock or not */
        if(!$this->isManageStock($item) || !$item->getBackToStock()) {
            return false;
        }

        return true;
    }

    /**
     * @param $warehouseId
     * @param $productId
     *
     * @return array
     */
    protected function getStock($warehouseId, $productId)
    {
        try {
            $warehouseProductCollection = $this->getWarehouseProductCollection()->selectAllStocks();
            $warehouseProduct = $warehouseProductCollection
                ->addFieldToFilter('website_id', $warehouseId) // MageStore use warehouse ID as website ID
                ->addFieldToFilter('product_id', $productId)
                ->setPageSize(1)
                ->setCurPage(1);

            return $warehouseProduct->getFirstItem()->getData();
        } catch (\Exception $e) {
        }

        return [];
    }

    /**
     * @param      $sku
     * @param null $warehouseId
     *
     * @return \Magestore\InventorySuccess\Api\Data\Warehouse\ProductInterface[]
     */
    protected function getCurrentWarehouseItemsMap($sku, $warehouseId = null)
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter('sku', $sku);

        if ($warehouseId) {
            $searchCriteriaBuilder->addFilter('website_id', $warehouseId);
        }

        $searchCriteria = $searchCriteriaBuilder->create();
        $warehouseItems = $this->getWarehouseStockRepository()
            ->getList($searchCriteria)
            ->getItems();

        $itemMap = [];

        if ($warehouseItems) {
            foreach ($warehouseItems as $warehouseItem) {
                $itemMap[$warehouseItem->getWarehouseId()] = $warehouseItem;
            }
        }

        return $itemMap;
    }

    /**
     * Helper function that adds a FilterGroup to the collection.
     *
     * @param \Magento\Framework\Api\Search\FilterGroup $filterGroup
     * @param \Magestore\InventorySuccess\Model\ResourceModel\Warehouse\Product\Collection $collection
     * @return void
     */
    protected function addFilterGroupToCollection($filterGroup, $collection)
    {
        foreach ($filterGroup->getFilters() as $filter) {
            $conditionType = $filter->getConditionType() ? $filter->getConditionType() : 'eq';
            $field = $this->getRealFieldFromAlias($filter->getField());
            $collection->addFieldToFilter($field, [$conditionType => $filter->getValue()]);
        }
    }

    /**
     * @param $field
     *
     * @return mixed|string
     */
    protected function getRealFieldFromAlias($field)
    {
        switch ($field) {
            case 'product_sku':
                $field = "catalog_product.sku";
                break;
            default:
                break;
        }
        return $field;
    }

    /**
     * @return \Magestore\InventorySuccess\Api\Warehouse\Location\MappingManagementInterface
     */
    protected function getMappingManagement()
    {
        return $this->objectManager->create('Magestore\InventorySuccess\Api\Warehouse\Location\MappingManagementInterface');
    }

    /**
     * @return \Magestore\InventorySuccess\Api\Warehouse\WarehouseManagementInterface
     */
    protected function getWarehouseManagement()
    {
        return $this->objectManager->create('Magestore\InventorySuccess\Api\Warehouse\WarehouseManagementInterface');
    }

    /**
     * @return mixed|\Magestore\InventorySuccess\Model\ResourceModel\Warehouse\Product\Collection
     */
    protected function getWarehouseProductCollection()
    {
        return $this->objectManager->create('Magestore\InventorySuccess\Model\ResourceModel\Warehouse\Product\Collection');
    }

    /**
     * @return mixed|\Magestore\InventorySuccess\Model\Warehouse\WarehouseStockRegistry
     */
    protected function getWarehouseStockRegistry()
    {
        return $this->objectManager->create('Magestore\InventorySuccess\Model\Warehouse\WarehouseStockRegistry');
    }

    /**
     * @return mixed|\Magestore\InventorySuccess\Model\Warehouse\WarehouseStockRepository
     */
    protected function getWarehouseStockRepository()
    {
        return $this->objectManager->create('Magestore\InventorySuccess\Model\Warehouse\WarehouseStockRepository');
    }
}
