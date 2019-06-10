<?php
/**
 * Created by Hung Nguyen - hungnh@smartosc.com
 * Date: 03/12/2018
 * Time: 15:21
 */

namespace SM\Integrate\Observer\StoreCredit\Magento2EE;

use Magento\CustomerBalance\Model\Balance\History;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\Integrate\Helper\Data;

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


    /**
     * @var SM\Integrate\Helper\Data
     */
    private $integrateHelper;

    public function __construct(
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        Data $integrateHelper
    ) {
        $this->storeManager    = $storeManager;
        $this->objectManager   = $objectManager;
        $this->integrateHelper = $integrateHelper;
    }

    /**
     * @param Observer $observer
     *
     * @return \SM\Integrate\Observer\StoreCredit\Magento2EE\CreditmemoSaveAfterObserver
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(Observer $observer)
    {
        if ($this->integrateHelper->isExistStoreCreditMagento2EE()
            && $this->integrateHelper->isIntegrateStoreCredit()) {
            $creditmemo = $observer->getEvent()->getCreditmemo();
            $order      = $creditmemo->getOrder();

            $creditmemo->setCustomerBalanceRefundFlag(true)
                       ->setCustomerBalTotalRefunded($creditmemo->getCustomerBalanceAmount())
                       ->setBsCustomerBalTotalRefunded($creditmemo->getBaseCustomerBalanceAmount());

            if ($creditmemo->getCustomerBalanceReturnMax() === null) {
                $customerBalanceReturnMax = 0;
            } else {
                $customerBalanceReturnMax = $creditmemo->getCustomerBalanceReturnMax();
            }

            if ((double)(string)$creditmemo->getCustomerBalTotalRefunded() > (double)(string)$customerBalanceReturnMax) {
                throw new LocalizedException(__('You can\'t use more store credit than the order amount.'));
            }
            //doing actual refund to customer balance if user have submitted refund form
            if ($creditmemo->getCustomerBalanceRefundFlag()) {
                $order->setBsCustomerBalTotalRefunded(
                    $order->getBsCustomerBalTotalRefunded() + $creditmemo->getBsCustomerBalTotalRefunded()
                );
                $order->setCustomerBalTotalRefunded(
                    $order->getCustomerBalTotalRefunded() + $creditmemo->getCustomerBalTotalRefunded()
                );

                $websiteId = $this->storeManager->getStore($order->getStoreId())->getWebsiteId();

                $this->getStoreCreditFactory()->create()->setCustomerId(
                    $order->getCustomerId()
                )->setWebsiteId(
                    $websiteId
                )->setAmountDelta(
                    $creditmemo->getBsCustomerBalTotalRefunded()
                )->setHistoryAction(
                    History::ACTION_REFUNDED
                )->setOrder(
                    $order
                )->setCreditMemo(
                    $creditmemo
                )->save();
            }
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
