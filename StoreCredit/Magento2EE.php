<?php
/**
 * Created by Hung Nguyen - hungnh@smartosc.com
 * Date: 03/12/2018
 * Time: 10:16
 */

namespace SM\Integrate\StoreCredit;

use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\Integrate\Data\StoreCreditQuoteData;
use SM\Integrate\Helper\Data;
use SM\Integrate\StoreCredit\Contract\AbstractStoreCreditIntegrate;
use SM\Integrate\StoreCredit\Contract\StoreCreditIntegrateInterface;

class Magento2EE extends AbstractStoreCreditIntegrate implements StoreCreditIntegrateInterface
{

    /**
     * @var \SM\Integrate\Helper\Data
     */
    protected $integrateHelperData;

    protected $storeManager;

    /**
     * Store Credit factory
     *
     * @var \Magento\CustomerBalance\Model\BalanceFactory
     */
    protected $storeCreditFactory;

    protected $storeCreditInstance;

    public function __construct(
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        Data $integrateHelperData
    ) {
        $this->storeManager       = $storeManager;
        $this->integrateHelperData = $integrateHelperData;
        parent::__construct($objectManager);
    }

    protected function getStoreCreditFactory()
    {
        if (is_null($this->storeCreditFactory)) {
            $this->storeCreditFactory = $this->objectManager->get('Magento\CustomerBalance\Model\BalanceFactory');
        }
        return $this->storeCreditFactory;
    }

    /**
     * @param      $customer
     * @param null $scope
     *
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getStoreCreditCollection($customer, $scope = null)
    {
        if (is_null($scope)) {
            $scope = $this->storeManager->getWebsite()->getId();
        }
        if (is_null($this->storeCreditInstance)) {
            if ($customer && $customer->getId()) {
                $this->storeCreditInstance = $this->getStoreCreditFactory()->create()->setCustomerId(
                    $customer->getId()
                )->setWebsiteId(
                    $scope
                )->loadByCustomer()->getAmount();
            }
        }
        return $this->storeCreditInstance;
    }

    /**
     * @param      $customer
     * @param null $scope
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCurrentStoreCreditBalance($customer, $scope = null)
    {
        if (is_null($scope)) {
            $scope = $this->storeManager->getWebsite()->getId();
        }
        if (is_null($this->storeCreditInstance)) {
            if ($customer && $customer->getId()) {
                $this->storeCreditInstance = $this->getStoreCreditFactory()->create()->setCustomerId(
                    $customer->getId()
                )->setWebsiteId(
                    $scope
                )->loadByCustomer()->getAmount();
            }
        }
        return $this->storeCreditInstance;
    }

    /**
     * @param $data
     *
     * @return void
     */
    public function saveStoreCreditDataBeforeQuoteCollect($data)
    {
        if (isset($data['use_store_credit']) && $data['use_store_credit'] == true) {
            /** @var  \Magento\Quote\Model\Quote $quote */
            $quote = $this->getQuote();
            $quote->getShippingAddress()->setCollectShippingRates(true);

            $quote->setUseCustomerBalance(true);
        }
    }

    /**
     * @return StoreCreditQuoteData
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getQuoteStoreCreditData()
    {
        // TODO: Implement getRPDataAfterQuoteCollect() method.
        $customerStoreCreditDetail = $this->getStoreCreditCollection($this->getQuote()->getCustomer());

        $quoteRpData      = new StoreCreditQuoteData();
        $quoteRpData->addData(
            [
                'use_store_credit'                  => $this->getQuote()->getUseCustomerBalance() === true,
                'customer_balance_currency'         => $customerStoreCreditDetail,
                'customer_balance_base_currency'    => $customerStoreCreditDetail,
                'store_credit_discount_amount'      => $this->getQuote()->getData('customer_balance_amount_used'),
                'base_store_credit_discount_amount' => $this->getQuote()->getData('base_customer_bal_amount_used')
            ]
        );
        return $quoteRpData;
    }
}
