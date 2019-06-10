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

    public function __construct(
        RetailTransactionFactory $transactionFactory,
        Data $shiftHelperData,
        PaymentHelper $paymentHelper,
        \SM\Integrate\Helper\Data $integrateHelper
    ) {
        $this->shiftHelperData          = $shiftHelperData;
        $this->retailTransactionFactory = $transactionFactory;
        $this->paymentHelper            = $paymentHelper;
        $this->integrateHelper          = $integrateHelper;
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

        if ($this->integrateHelper->isIntegrateStoreCredit()
            && $this->integrateHelper->isExistStoreCreditMagento2EE()) {
            if ($order->getData('retail_id') && $order->getData('customer_balance_amount')) {
                $outletId             = $order->getData('outlet_id');
                $registerId           = $order->getData('register_id');
                $currentShift         = $this->shiftHelperData->getShiftOpening($outletId, $registerId);
                $storeCreditPaymentId = $this->paymentHelper->getPaymentIdByType(
                    RetailPayment::STORE_CREDIT_PAYMENT_TYPE
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
                                ->setData('order_id', $order->getEntityId())
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
