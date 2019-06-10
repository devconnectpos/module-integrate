<?php
/**
 * Created by Hung Nguyen - hungnh@smartosc.com
 * Date: 03/12/2018
 * Time: 17:20
 */

namespace SM\Integrate\Observer\StoreCredit\Magento2EE\RefundToStoreCredit;

use Magento\CustomerBalance\Model\Balance\History;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class CreditmemoSaveAfterObserver implements ObserverInterface
{

    /**
     * Store Credit factory
     *
     * @var \Magento\CustomerBalance\Model\BalanceFactory
     */
    protected $balanceFactory;

    protected $objectManager;
    protected $storeManager;

    public function __construct(
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
        $this->objectManager = $objectManager;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $isRefundToStoreCredit = $observer->getEvent()->getRefundToStoreCredit();
        $order = $creditmemo->getOrder();

        $websiteId = $this->storeManager->getStore($order->getStoreId())->getWebsiteId();

        if ($isRefundToStoreCredit == true) {
            $this->getStoreCreditFactory()->create()->setCustomerId(
                $order->getCustomerId()
            )->setWebsiteId(
                $websiteId
            )->setAmountDelta(
                $creditmemo->getBaseGrandTotal()
            )->setHistoryAction(
                History::ACTION_CREATED
            )->setAdditionalInfo(
                'Refund to Store Credit Order #' . $order->getIncrementId()
            )->save();
        }

        return $this;
    }

    protected function getStoreCreditFactory()
    {
        if (is_null($this->balanceFactory)) {
            $this->balanceFactory = $this->objectManager->get('Magento\CustomerBalance\Model\BalanceFactory');
        }
        return $this->balanceFactory;
    }
}
