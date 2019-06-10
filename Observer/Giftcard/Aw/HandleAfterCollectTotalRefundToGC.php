<?php

namespace SM\Integrate\Observer\Giftcard\Aw;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\SalesRule\Model\ResourceModel\Rule\Collection;
use SM\Integrate\Helper\Data;
use SM\Sales\Repositories\OrderManagement;

class HandleAfterCollectTotalRefundToGC implements ObserverInterface
{

    /**
     * @var SM\Integrate\Helper\Data
     */
    private $integrateHelper;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * HandleAfterCollectTotalRefundToGC constructor.
     *
     * @param \SM\Integrate\Helper\Data             $integrateHelper
     * @param \Magento\Framework\Registry           $registry
     */
    public function __construct(
        Data $integrateHelper,
        Registry $registry
    ) {
        $this->integrateHelper = $integrateHelper;
        $this->registry      = $registry;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        $collection = $observer->getData('collection');
        $isUsingRefundToGCProduct = $this->registry->registry(OrderManagement::USING_REFUND_TO_GIFT_CARD);
        if ($collection instanceof Collection) {
            if ($isUsingRefundToGCProduct) {
                $collection->clear();
            }
        }
    }
}
