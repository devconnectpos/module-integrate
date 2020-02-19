<?php

namespace SM\Integrate\StorePickUp;

use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\Integrate\Data\XStorePickUpLocation;
use SM\Integrate\Helper\Data;
use SM\XRetail\Helper\DataConfig;

class MageWorx
{
    /**
     * @var \SM\Integrate\Helper\Data
     */
    protected $integrateHelperData;

    protected $storeManager;

    protected  $objectManager;

    protected $storePickUpCollection;

    protected $storePickUpFactory;


    protected $_transformLocationData
        = [
            "entity_id" => "entity_id",
            "location_name" => "name",
            "location_code" => "code",
            "email" => "email",
            "phone_number" => "phone_number",
            "city" => "city",
            "country_id" => "country_id",
            "postcode" => "postcode",
            "address" => "address",
            "region" => "region",
            "region_id" => "region_id",
            "is_active" => "is_active",
            "store_ids" => "store_ids",
        ];

    public function __construct(
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        Data $integrateHelperData
    ) {
        $this->storeManager       = $storeManager;
        $this->integrateHelperData = $integrateHelperData;
        $this->objectManager    = $objectManager;
    }

    protected function getStorePickUpCollection()
    {
        if (is_null($this->storePickUpCollection)) {
            $this->storePickUpCollection = $this->objectManager->get('MageWorx\Locations\Model\ResourceModel\Location\Collection');
        }
        return $this->storePickUpCollection;
    }

    protected function getStorePickUpFactory()
    {
        if (is_null($this->storePickUpFactory)) {
            $this->storePickUpFactory = $this->objectManager->get('MageWorx\Locations\Api\LocationRepositoryInterface');
        }
        return $this->storePickUpFactory;
    }

    /**
     * @param $searchCriteria
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    public function getLocationCollection($searchCriteria)
    {
        $collection = $this->objectManager->create('MageWorx\Locations\Api\LocationRepositoryInterface')->getListLocation();

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
    public function loadStoreLocationData($searchCriteria)
    {
        // TODO: Implement loadStoreLocationData() method.
        $searchResult   = new \SM\Core\Api\SearchResult();
        $items          = [];
        $size           = 0;
        $lastPageNumber = 0;
        if ($this->integrateHelperData->isIntegrateStorePickUpExtension() && $this->integrateHelperData->isExistMageWorx()) {
            $locationCollection = $this->getLocationCollection($searchCriteria);
            $size                = $locationCollection->getSize();
            $lastPageNumber      = $locationCollection->getLastPageNumber();

            if ($locationCollection->getLastPageNumber() < $searchCriteria->getData('currentPage')) {

            }
            else {
                foreach ($locationCollection as $item) {
                    $_data = new XStorePickUpLocation();
                    foreach ($this->_transformLocationData as $k => $v) {
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
}
