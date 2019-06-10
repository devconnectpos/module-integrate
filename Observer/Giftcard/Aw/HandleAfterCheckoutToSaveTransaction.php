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
     * HandleAfterCheckoutToSaveTransaction constructor.
     *
     * @param \SM\Shift\Model\RetailTransactionFactory $transactionFactory
     * @param \SM\Shift\Helper\Data                    $shiftHelperData
     * @param \SM\Payment\Helper\PaymentHelper         $paymentHelper
     */
    public function __construct(
        RetailTransactionFactory $transactionFactory,
        Data $shiftHelperData,
        PaymentHelper $paymentHelper
    ) {
        $this->shiftHelperData          = $shiftHelperData;
        $this->retailTransactionFactory = $transactionFactory;
        $this->paymentHelper            = $paymentHelper;
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
                            ->setData('order_id', $order->getEntityId())
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
