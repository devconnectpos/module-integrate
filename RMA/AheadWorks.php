<?php

namespace SM\Integrate\RMA;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;
use SM\Integrate\Data\XCustomFields;
use SM\Integrate\Helper\Data;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;

class AheadWorks extends ServiceAbstract
{
    /**
     * @var \SM\Integrate\Helper\Data
     */
    protected $integrateHelperData;

    protected $storeManager;

    protected $objectManager;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    protected $_transformCustomFieldsData
        = [
            "id" => "id",
            "name" => "name",
            "type" => "type",
            "refers" => "refers",
            "website_ids" => "website_ids",
            "visible_for_status_ids" => "visible_for_status_ids",
            "is_required" => "is_required",
            "is_active" => "is_active",
            "is_display_in_label" => "is_display_in_label",
            "options" => "options",
            "frontend_labels" => "frontend_labels",
            "storefront_label" => "storefront_label"
        ];

    protected $imageHelper;

    public function __construct(
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        Data $integrateHelperData,
        DataConfig $dataConfig,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderFactory $orderFactory,
        \Magento\Catalog\Helper\Image $imageHelper,
        RequestInterface $requestInterface
    ) {
        $this->integrateHelperData = $integrateHelperData;
        $this->objectManager    = $objectManager;
        $this->orderFactory = $orderFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->imageHelper    = $imageHelper;
        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    public function loadOrderRMAData($searchCriteria)
    {
        $searchResult   = new \SM\Core\Api\SearchResult();
        $items          = [];
        $size           = 0;
        $lastPageNumber = 0;
        $orderId        = $searchCriteria->getData('orderId');
        if ($this->integrateHelperData->isIntegrateRMAExtension() && $this->integrateHelperData->isExistAheadWorksRMA()) {
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);
            $isAllowOrder = $this->isAllowedForOrder($order);
            $rmaItems = [];
            $orderItems = $this->objectManager->get('Aheadworks\Rma\Model\Request\Order\Item')->getOrderItemsToRequest($order->getEntityId());
            foreach ($orderItems as $orderItem) {
                $itemMaxCount = $this->objectManager->get('Aheadworks\Rma\Model\Request\Order\Item')->getItemMaxCount($orderItem);
                if ($itemMaxCount > 0) {
                    $product = $this->objectManager->get('Magento\Catalog\Api\ProductRepositoryInterfaceFactory')->create()->getById($orderItem->getProduct()->getId());
                    $imageUrl = '';
                    if (!empty($product->getData('image'))) {
                        $imageUrl = $this->imageHelper->init($product, 'product_page_image_small', ['type' => 'thumbnail'])
                            ->resize(200)
                            ->setImageFile($product->getData('image'))
                            ->getUrl();
                    }
                    $rmaItems[] = [
                        'id' => $orderItem->getId(),
                        'item_id' => $orderItem->getItemId(),
                        'sku' => $orderItem->getData('sku'),
                        'name' => $orderItem->getData('name'),
                        'price' => $orderItem->getData('price'),
                        'max_qty' => $itemMaxCount,
                        'image' => $imageUrl
                    ];
                }
            }
            $order->setData('orderItems', $rmaItems);
            $order->setData('isAllowOrder', $isAllowOrder);
            $items[] = $order->getData();
        }
        return $searchResult
            ->setItems($items)
            ->setTotalCount($size)
            ->setLastPageNumber($lastPageNumber);
    }

    /**
     * Check is allowed for order or not
     *
     * @param Order $order
     * @return bool
     */
    public function isAllowedForOrder(Order $order)
    {
        $isAllowedForOrder = $this->objectManager->get('Aheadworks\Rma\Model\Request\Order')->isAllowedForOrder($order);
        if (!$isAllowedForOrder) {
            return $isAllowedForOrder;
        }

        $orderItems = $this->objectManager->get('Aheadworks\Rma\Model\Request\Order\Item')->getOrderItemsToRequest($order->getEntityId());
        foreach ($orderItems as $orderItem) {
            if ($this->objectManager->get('Aheadworks\Rma\Model\Request\Order\Item')->getItemMaxCount($orderItem) > 0) {
                return true;
            }
        }

        return false;
    }

    public function loadListCustomFields($searchCriteria)
    {
        // TODO: Implement loadStoreLocationData() method.
        $searchResult   = new \SM\Core\Api\SearchResult();
        $items          = [];
        $size           = 0;
        $lastPageNumber = 0;
        if ($this->integrateHelperData->isIntegrateRMAExtension() && $this->integrateHelperData->isExistAheadWorksRMA()) {
            $this->searchCriteriaBuilder
                ->addFilter('editable_or_visible_for_status', -1)
                ->addFilter('options', 'enabled')
                ->addFilter('is_active', 1);
            $this->searchCriteriaBuilder->setCurrentPage(is_nan($searchCriteria->getData('currentPage')) ? 1 : $searchCriteria->getData('currentPage'));
            $this->searchCriteriaBuilder->setPageSize(
                is_nan($searchCriteria->getData('pageSize')) ? DataConfig::PAGE_SIZE_LOAD_DATA : $searchCriteria->getData('pageSize')
            );
            $customFields = $this->objectManager->get('Aheadworks\Rma\Api\CustomFieldRepositoryInterface')->getList($this->searchCriteriaBuilder->create());
            $size                = $customFields->getTotalCount();
            if (0 === $size) {
                $lastPageNumber = 1;
            } elseif ($searchCriteria->getData('pageSize')) {
                $lastPageNumber = ceil($size / $searchCriteria->getData('pageSize'));
            } else {
                $lastPageNumber = 1;
            }
            if ($lastPageNumber >= $searchCriteria->getData('currentPage')) {
                foreach ($customFields->getItems() as $customField) {
                    $options = [];
                    $frontedLabels = [];
                    foreach ($customField['options'] as $option) {
                        $storeLabels = [];
                        foreach ($option['store_labels'] as $store_label) {
                            $storeLabels[] = [
                                'store_id'=>$store_label->getStoreId(),
                                'label'=>$store_label->getValue()
                            ];
                        }
                        $option->setData('store_labels', $storeLabels);
                        $options[] = $option->getData();
                    }
                    foreach ($customField['frontend_labels'] as $frontend_label) {
                        $frontedLabels[] = [
                            'store_id'=>$frontend_label->getStoreId(),
                            'label'=>$frontend_label->getValue()
                        ];
                    }
                    $customField->setData('options', $options);
                    $customField->setData('frontend_labels', $frontedLabels);
                    $_data = new XCustomFields();
                    foreach ($this->_transformCustomFieldsData as $k => $v) {
                        $_data->setData($k, $customField->getData($v));
                    }
                    $items[] = $_data;
                }
            }
        }
        return $searchResult
            ->setItems($items)
            ->setTotalCount($size)
            ->setLastPageNumber($lastPageNumber);
    }

    public function createRequestRMA()
    {
        $searchResult   = new \SM\Core\Api\SearchResult();
        $items = [];
        if ($this->integrateHelperData->isIntegrateRMAExtension() && $this->integrateHelperData->isExistAheadWorksRMA()) {
            $order = $this->getRequest()->getParam('order');
            $requestRMA = $this->objectManager->get('Aheadworks\Rma\Api\Data\RequestInterfaceFactory')->create();
            $requestRMA->setIncrementId((int)$order['increment_id']);
            $requestRMA->setOrderId((int)$order['entity_id']);
            $requestRMA->setStoreId((int)$order['store_id']);
            $requestRMA->setCustomerId($order['customer_id']);
            $requestRMA->setCustomerName($order['customer_firstname'] . " " . ($order['customer_middlename'] === null ? '' : $order['customer_middlename']) . " " . $order['customer_lastname']);
            $requestRMA->setCustomerEmail($order['customer_email']);
            $requestRMA->setCustomFields($this->preparedCustomFields($order['customFields']));
            $requestRMA->setOrderItems($this->preparedOrderItems($order['orderItems']));
            if (count($requestRMA->getOrderItems()) > 0) {
                $request = $this->objectManager->get('Aheadworks\Rma\Model\Service\RequestService')->createRequest($requestRMA, true, $order['store_id']);
                $items[] = $request->getData();
            }
        }
        return $searchResult
            ->setItems($items);
    }

    public function preparedCustomFields($customFields)
    {
        $customFieldValues = [];
        foreach ($customFields as $key => $value) {
            $customFieldEntity = $this->objectManager->get('Aheadworks\Rma\Api\Data\RequestCustomFieldValueInterfaceFactory')->create();
            $customFieldEntity->setFieldId($key);
            $customFieldEntity->setValue($value);
            $customFieldValues[] = $customFieldEntity;
        }

        if (!isset($customFieldValues[2]['field_id'])) {
            $customFieldValues[2]['field_id']= 4;
            $customFieldValues[2]['value']= '';
        }
        return $customFieldValues;
    }

    public function preparedOrderItems($orderItems)
    {
        $orderItemValues = [];
        foreach ($orderItems as $orderItem) {
            if ($orderItem['return_qty'] > 0) {
                $itemEntity = $this->objectManager->get('Aheadworks\Rma\Api\Data\RequestItemInterfaceFactory')->create();
                $itemEntity->setItemId((int)$orderItem['item_id']);
                $itemEntity->setQty((int)$orderItem['return_qty']);
                $itemEntity->setCustomFields($this->preparedCustomFields($orderItem['customFields']));
                $orderItemValues[] = $itemEntity;
            }
        }
        return $orderItemValues;
    }
}
