<?php
/**
 * Created by Hung Nguyen - hungnh@smartosc.com
 * Date: 03/12/2018
 * Time: 17:15
 */

namespace SM\Integrate\Observer\StoreCredit\Magento2EE;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use SM\Payment\Helper\PaymentHelper;
use SM\Payment\Model\RetailPayment;
use SM\Shift\Helper\Data;
use SM\Shift\Model\RetailTransactionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\Currency;

class HandleAfterCheckoutToSaveTransaction implements ObserverInterface
{


    /**
     * @var \SM\Shift\Model\RetailTransactionFactory
     */
    private $retailTransactionFactory;
    /**
     * @var \SM\Shift\Helper\Data
     */
    private $shiftHelperData;
    /**
     * @var \SM\Payment\Helper\PaymentHelper
     */
    private $paymentHelper;
    /**
     * @var \SM\Integrate\Helper\Data
     */
    private $integrateHelper;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Magento\Directory\Model\Currency
     */
    private $currencyModel;

    public function __construct(
        RetailTransactionFactory $transactionFactory,
        Data $shiftHelperData,
        PaymentHelper $paymentHelper,
        StoreManagerInterface $storeManager,
        Currency $currencyModel,
        \SM\Integrate\Helper\Data $integrateHelper
    ) {
        $this->shiftHelperData          = $shiftHelperData;
        $this->retailTransactionFactory = $transactionFactory;
        $this->paymentHelper            = $paymentHelper;
        $this->integrateHelper          = $integrateHelper;
        $this->storeManager             = $storeManager;
        $this->currencyModel            = $currencyModel;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getData('order');
        $baseCurrencyCode    = $this->storeManager->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = $this->storeManager->getStore($order->getData('store_id'))->getCurrentCurrencyCode();
        $allowedCurrencies   = $this->currencyModel->getConfigAllowCurrencies();
        $rates               = $this->currencyModel->getCurrencyRates($baseCurrencyCode, array_values($allowedCurrencies));
        if ($this->integrateHelper->isIntegrateStoreCredit()
            && ($this->integrateHelper->isExistStoreCreditMagento2EE() || $this->integrateHelper->isExistStoreCreditAheadworks())) {
            if ($order->getData('retail_id') && $order->getData('customer_balance_amount')) {
                $outletId             = $order->getData('outlet_id');
                $registerId           = $order->getData('register_id');
                $currentShift         = $this->shiftHelperData->getShiftOpening($outletId, $registerId);
                $storeCreditPaymentId = $this->paymentHelper->getPaymentIdByType(
                    RetailPayment::STORE_CREDIT_PAYMENT_TYPE,
                    $order->getData('register_id')
                );
                if ($currentShift->getData('id') && $storeCreditPaymentId) {
                    $transaction = $this->getRetailTransactionModel();
                    $transaction->setData('payment_id', $storeCreditPaymentId)
                                ->setData('shift_id', $currentShift->getData('id'))
                                ->setData('outlet_id', $outletId)
                                ->setData('register_id', $registerId)
                                ->setData('payment_title', 'Store Credit')
                                ->setData('payment_type', RetailPayment::STORE_CREDIT_PAYMENT_TYPE)
                                ->setData('amount', $order->getData('customer_balance_amount'))
                                ->setData('is_purchase', 1)
                                ->setData('user_name', $order->getData('user_name'))
                                ->setData('order_id', $order->getEntityId())
                                ->setData('base_amount', isset($rates[$currentCurrencyCode]) && $rates[$currentCurrencyCode] != 0 ? $order->getData('customer_balance_amount')
                                    / $rates[$currentCurrencyCode] : null)
                                ->save();
                }
            }
        }
    }

    /**
     * @return \SM\Shift\Model\RetailTransaction
     */
    protected function getRetailTransactionModel()
    {
        return $this->retailTransactionFactory->create();
    }
}
