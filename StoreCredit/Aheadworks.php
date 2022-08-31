<?php

namespace SM\Integrate\StoreCredit;

use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\Integrate\Helper\Data;
use SM\Integrate\StoreCredit\Contract\AbstractStoreCreditIntegrate;
use SM\Integrate\StoreCredit\Contract\StoreCreditIntegrateInterface;
use SM\Integrate\Data\StoreCreditQuoteDataFactory;

class Aheadworks extends AbstractStoreCreditIntegrate implements StoreCreditIntegrateInterface
{
    /**
     * @var \SM\Integrate\Helper\Data
     */
    protected $integrateHelperData;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var StoreCreditQuoteDataFactory
     */
    protected $storeCreditQuoteDataFactory;

    public function __construct(
        ObjectManagerInterface      $objectManager,
        StoreManagerInterface       $storeManager,
        StoreCreditQuoteDataFactory $storeCreditQuoteDataFactory,
        Data                        $integrateHelperData
    )
    {
        $this->storeManager = $storeManager;
        $this->integrateHelperData = $integrateHelperData;
        $this->storeCreditQuoteDataFactory = $storeCreditQuoteDataFactory;
        parent::__construct($objectManager);
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
        if (is_object($customer)) {
            $customer = $customer->getId();
        }

        return $this->getCurrentStoreCreditBalance($customer, $scope);
    }

    /**
     * @inheritDoc
     */
    public function saveStoreCreditDataBeforeQuoteCollect($data)
    {
        if (!isset($data['use_store_credit']) || empty($data['use_store_credit'])) {
            return;
        }

        $this->getStoreCreditCartService()->set($this->getQuote()->getId());
    }

    /**
     * @inheritDoc
     */
    public function getQuoteStoreCreditData()
    {
        /** @var \SM\Integrate\Data\StoreCreditQuoteData $quoteStoreCreditData */
        $quoteStoreCreditData = $this->storeCreditQuoteDataFactory->create();
        $quoteStoreCreditData->addData([
            'use_store_credit' => $this->getQuote()->getData('aw_use_store_credit'),
            'customer_balance_currency' => $this->getQuote()->getData('quote_currency_code'),
            'customer_balance_base_currency' => $this->getQuote()->getData('base_currency_code'),
            'store_credit_discount_amount' => $this->getQuote()->getData('aw_store_credit_amount'),
            'base_store_credit_discount_amount' => $this->getQuote()->getData('base_aw_store_credit_amount')
        ]);

        return $quoteStoreCreditData;
    }

    /**
     * @inheritDoc
     */
    public function getCurrentStoreCreditBalance($customerId, $scope = null)
    {
        return $this->getCustomerStoreCreditService()->getCustomerStoreCreditBalance($customerId);
    }

    /**
     * @param \Magento\Customer\Api\Data\CustomerInterface|\Magento\Customer\Model\Customer $customer
     * @param float $amountDelta
     * @param int $websiteId
     * @param int $storeId
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateCustomerStoreCreditBalance($customer, $websiteId, $storeId, $amountDelta)
    {
        $amountDelta = floatval($amountDelta);
        $this->getCustomerStoreCreditService()->resetCustomer();
        $this->getCustomerStoreCreditService()->saveAdminTransaction([
            "customer_id" => $customer->getId(),
            "customer_name" => $customer->getFirstname() . " " . $customer->getLastname(),
            "customer_email" => $customer->getEmail(),
            "website_id" => $websiteId,
            "balance" => $amountDelta,
            "comment_to_customer" => "Adjusted from ConnectPOS",
            "comment_to_admin" => "Adjusted from ConnectPOS"
        ]);
    }

    /**
     * @return \Aheadworks\StoreCredit\Model\Service\CustomerStoreCreditService|mixed
     */
    protected function getCustomerStoreCreditService()
    {
        return $this->objectManager->get('Aheadworks\StoreCredit\Model\Service\CustomerStoreCreditService');
    }

    /**
     * @return \Aheadworks\StoreCredit\Model\Service\StoreCreditCartService|mixed
     */
    protected function getStoreCreditCartService()
    {
        return $this->objectManager->get('Aheadworks\StoreCredit\Model\Service\StoreCreditCartService');
    }
}
