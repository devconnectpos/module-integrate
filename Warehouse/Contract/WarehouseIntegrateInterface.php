<?php
/**
 * Created by IntelliJ IDEA.
 * User: vjcspy
 * Date: 4/10/17
 * Time: 11:37 AM
 */

namespace SM\Integrate\Warehouse\Contract;

interface WarehouseIntegrateInterface
{

    /**
     * @param $collection
     * @param $warehouseId
     * @param $searchCriteria
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    public function filterProductCollectionByWarehouse($collection, $warehouseId);

    /**
     * @param $warehouseId
     *
     * @return array
     */
    public function getListProductByWarehouse($warehouseId);

    /**
     * @param $searchCriteria
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    public function getWarehouseCollection($searchCriteria);

    /**
     * @param $searchCriteria
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    public function getWarehouseItemCollection($searchCriteria);

    /**
     * @param      $product
     * @param      $warehouseId
     * @param null $scopeId
     *
     * @return mixed
     */
    public function getStockItem($product, $warehouseId, $scopeId = null);

    /**
     * @param $searchCriteria
     *
     * @return \SM\Core\Api\SearchResult
     */
    public function loadWarehouseData($searchCriteria);

    /**
     * @param int $productId
     * @param int $warehouseId
     *
     * @return array
     */
    public function getWarehouseStockItem($productId, $warehouseId);

    /**
     * @param $product
     *
     * @return boolean
     */
    public function isProductSalable($product);

    /**
     * @param \SM\RefundWithoutReceipt\Model\RefundWithoutReceiptItem $item
     * @param \SM\RefundWithoutReceipt\Model\RefundWithoutReceiptTransaction $transaction
     *
     * @return mixed
     */
    public function returnItemToStock($item, $transaction);
}
