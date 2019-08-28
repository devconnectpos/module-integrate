<?php
/**
 * Created by IntelliJ IDEA.
 * User: vjcspy
 * Date: 4/10/17
 * Time: 3:09 PM
 */

namespace SM\Integrate\Warehouse\Contract;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use SM\Core\Api\SearchResult;
use SM\Integrate\Data\XWarehouse;
use SM\Integrate\Helper\Data as IntegrateHelper;

/**
 * Class AbstractWarehouseIntegrate
 *
 * @package SM\Integrate\Warehouse\Contract
 */
abstract class AbstractWarehouseIntegrate
{

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;
    /**
     * @var \SM\Integrate\Helper\Data
     */
    protected $integrateData;
    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * AbstractWarehouseIntegrate constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface       $objectManager
     * @param \SM\Integrate\Helper\Data                       $integrateData
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        IntegrateHelper $integrateData,
        ProductRepositoryInterface $productRepository
    ) {
        $this->integrateData = $integrateData;
        $this->objectManager = $objectManager;
        $this->productRepository = $productRepository;
    }

    protected $transformWarehouseData
        = [
            "warehouse_id"   => "warehouse_id",
            "warehouse_name" => "warehouse_name",
            "warehouse_code" => "warehouse_code",
            "contact_email"  => "contact_email",
            "telephone"      => "telephone",
            "city"           => "city",
            "country_id"     => "country_id",
            "region"         => "region",
            "region_id"      => "region_id",
            "is_active"      => "is_active",
            "is_primary"     => "is_primary",
            "company"        => "company",
            "street1"        => "street1",
            "street2"        => "street2",
            "fax"            => "fax",
        ];

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

                    foreach ($this->transformWarehouseData as $k => $v) {
                        $_data->setData($k, $item->getData($v));
                    }

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
     * @param int $productId
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function triggerRealTimeProduct($productId)
    {
        try {
            $product = $this->productRepository->getById($productId);
            $observer = $this->objectManager->create('SM\Performance\Observer\ModelAfterSave');
            $ob = new Observer();
            $ob->setData('object', $product);
            $observer->execute($ob);
        } catch (NoSuchEntityException $e) {
            throw new NoSuchEntityException();
        }
    }
}
