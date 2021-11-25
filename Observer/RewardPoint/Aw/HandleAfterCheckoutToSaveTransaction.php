<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 3/5/18
 * Time: 16:56
 */

namespace SM\Integrate\Observer\RewardPoint\Aw;

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
     * @var \Magento\Directory\Model\Currency
     */
    private $currencyModel;

    /**
     * HandleAfterCheckoutToSaveTransaction constructor.
     *
     * @param RetailTransactionFactory  $transactionFactory
     * @param Data                      $shiftHelperData
     * @param PaymentHelper             $paymentHelper
     * @param StoreManagerInterface     $storeManager
     * @param Currency                  $currencyModel
     * @param \SM\Integrate\Helper\Data $integrateHelper
     */
    public function __construct(
        RetailTransactionFactory $transactionFactory,
        Data $shiftHelperData,
        PaymentHelper $paymentHelper,
        StoreManagerInterface $storeManager,
        Currency $currencyModel,
        \SM\Integrate\Helper\Data $integrateHelper
    ) {
        $this->shiftHelperData = $shiftHelperData;
        $this->retailTransactionFactory = $transactionFactory;
        $this->paymentHelper = $paymentHelper;
        $this->integrateHelper = $integrateHelper;
        $this->storeManager = $storeManager;
        $this->currencyModel = $currencyModel;
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
        $quote = $observer->getData('quote');

        if (!$this->integrateHelper->isIntegrateRP() || !$order->getData('retail_id')) {
            return;
        }

        $baseCurrencyCode = $this->storeManager->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = $this->storeManager->getStore($order->getData('store_id'))->getCurrentCurrencyCode();
        $allowedCurrencies = $this->currencyModel->getConfigAllowCurrencies();
        $rates = $this->currencyModel->getCurrencyRates($baseCurrencyCode, array_values($allowedCurrencies));

        $magentoEECondition = $this->integrateHelper->isRewardPointMagento2EE() && $order->getData('reward_currency_amount');
        $aheadWorksCondition = $this->integrateHelper->isAHWRewardPoints() && $order->getData('aw_reward_points');
        $amastyCondition = $this->integrateHelper->isAmastyRewardPoints() && floatval($quote->getAmrewardsPoint()) > 0;
        $mirasvitCondition = $this->integrateHelper->isMirasvitRewardPoints();

        $spentAmount = 0;

        if ($magentoEECondition) {
            $spentAmount = floatval($order->getData('reward_currency_amount'));
        } elseif ($aheadWorksCondition) {
            $spentAmount = floatval($order->getData('aw_reward_points_amount'));
        } elseif ($amastyCondition) {
            $spentAmount = floatval($quote->getAmrewardsPoint());
        } elseif ($mirasvitCondition) {
            $spentAmount = floatval($quote->getData('reward_points_amount'));
        }

        $currencyRate = isset($rates[$currentCurrencyCode]) ? $rates[$currentCurrencyCode] : 1;
        $this->saveRewardPointsTransaction($order, $spentAmount, $currencyRate);
    }

    /**
     * @param $order
     * @param $spentAmount
     * @param $currencyRate
     *
     * @return \SM\Shift\Model\RetailTransaction|null
     * @throws \Exception
     */
    protected function saveRewardPointsTransaction($order, $spentAmount, $currencyRate)
    {
        $outletId = $order->getData('outlet_id');
        $registerId = $order->getData('register_id');
        $currentShift = $this->shiftHelperData->getShiftOpening($outletId, $registerId);
        $rewardPaymentId = $this->paymentHelper->getPaymentIdByType(RetailPayment::REWARD_POINT_PAYMENT_TYPE, $order->getData('register_id'));
        $baseSpentAmount = $spentAmount / $currencyRate;

        if ($currentShift->getData('id') && $rewardPaymentId) {
            $transaction = $this->getRetailTransactionModel();
            $transaction->setPaymentId($rewardPaymentId)
                ->setShiftId($currentShift->getData('id'))
                ->setOutletId($outletId)
                ->setRegisterId($registerId)
                ->setPaymentTitle('Reward Points')
                ->setPaymentType(RetailPayment::REWARD_POINT_PAYMENT_TYPE)
                ->setIsPurchase(1)
                ->setUsername($order->getData('user_name'))
                ->setOrderId($order->getEntityId())
                ->setAmount($spentAmount)
                ->setBaseAmount($baseSpentAmount)
                ->save();

            return $transaction;
        }

        return null;
    }

    /**
     * @return \SM\Shift\Model\RetailTransaction
     */
    protected function getRetailTransactionModel()
    {
        return $this->retailTransactionFactory->create();
    }
}
