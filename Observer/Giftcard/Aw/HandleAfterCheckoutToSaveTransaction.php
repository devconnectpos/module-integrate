<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 3/5/18
 * Time: 16:56
 */

namespace SM\Integrate\Observer\Giftcard\Aw;

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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var Magento\Directory\Model\Currency
     */
    private $currencyModel;

    /**
     * HandleAfterCheckoutToSaveTransaction constructor.
     * @param RetailTransactionFactory $transactionFactory
     * @param Data $shiftHelperData
     * @param StoreManagerInterface $storeManager
     * @param Currency $currencyModel
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(
        RetailTransactionFactory $transactionFactory,
        Data $shiftHelperData,
        StoreManagerInterface $storeManager,
        Currency $currencyModel,
        PaymentHelper $paymentHelper
    ) {
        $this->shiftHelperData          = $shiftHelperData;
        $this->retailTransactionFactory = $transactionFactory;
        $this->paymentHelper            = $paymentHelper;
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
        if ($order->getData('retail_id') && $order->getData('aw_giftcard_amount')) {
            $outletId          = $order->getData('outlet_id');
            $registerId        = $order->getData('register_id');
            $currentShift      = $this->shiftHelperData->getShiftOpening($outletId, $registerId);
            $giftCardPaymentId = $this->paymentHelper->getPaymentIdByType(RetailPayment::GIFT_CARD_PAYMENT_TYPE);
            if ($currentShift->getData('id') && $giftCardPaymentId) {
                $transaction = $this->getRetailTransactionModel();
                $transaction->setData('payment_id', $giftCardPaymentId)
                            ->setData('shift_id', $currentShift->getData('id'))
                            ->setData('outlet_id', $outletId)
                            ->setData('register_id', $registerId)
                            ->setData('payment_type', RetailPayment::GIFT_CARD_PAYMENT_TYPE)
                            ->setData('amount', $order->getData('aw_giftcard_amount'))
                            ->setData('is_purchase', 1)
                            ->setData('user_name', $order->getData('user_name'))
                            ->setData('order_id', $order->getEntityId())
                            ->setData('base_amount', isset($rates[$currentCurrencyCode]) && $rates[$currentCurrencyCode] != 0 ? $order->getData('aw_giftcard_amount') / $rates[$currentCurrencyCode] : null)
                            ->save();
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
