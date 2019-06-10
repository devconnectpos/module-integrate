<?php

namespace SM\Integrate\Observer\Giftcard\Aw;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use SM\Integrate\Helper\Data;
use SM\Sales\Repositories\OrderManagement;

class HandleTaxAfterCheckOutRefundToGC implements ObserverInterface
{

    /**
     * @var SM\Integrate\Helper\Data
     */
    private $integrateHelper;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

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
        $request = $observer->getData('request');
        $isUsingRefundToGCProduct = $this->registry->registry(OrderManagement::USING_REFUND_TO_GIFT_CARD);
        // fake customer tax class id đối với order có mua refund to giftcard
        if ($isUsingRefundToGCProduct && $request->getData('customer_class_id')) {
            $request->setData('customer_class_id', '1527499479');
        }
    }
}
