<?php
/**
 * Created by Hung Nguyen - hungnh@smartosc.com
 * Date: 02/10/2018
 * Time: 17:39
 */

namespace SM\Integrate\RewardPoint;

use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\Integrate\Data\RewardPointQuoteData;
use SM\Integrate\Helper\Data;
use SM\Integrate\RewardPoint\Contract\AbstractRPIntegrate;
use SM\Integrate\RewardPoint\Contract\RPIntegrateInterface;
use SM\Integrate\RewardPoint\Magento2EE\Earning;

class Magento2EE extends AbstractRPIntegrate implements RPIntegrateInterface
{

    /**
     * @var \SM\Integrate\Helper\Data
     */
    protected $integrateHelperData;

    protected $storeManager;
    /**
     * Reward pts model instance
     *
     * @var \Magento\Reward\Model\Reward
     */
    protected $rewardInstance = null;

    /**
     * Reward factory
     *
     * @var \Magento\Reward\Model\RewardFactory
     */
    protected $rewardFactory;
    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;
    /**
     * @var \SM\Integrate\RewardPoint\Magento2EE\Earning
     */
    private $earningCalculator;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Customer converter
     *
     * @var CustomerRegistry
     */
    protected $customerRegistry;

    /**
     * Magento2EE constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface          $objectManager
     * @param \Magento\Store\Model\StoreManagerInterface         $storeManager
     * @param \SM\Integrate\Helper\Data                          $integrateHelperData
     * @param \SM\Integrate\RewardPoint\Magento2EE\Earning       $earning
     * @param \Magento\Customer\Model\CustomerFactory            $customerFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param CustomerRegistry $customerRegistry
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        Data $integrateHelperData,
        Earning $earning,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        CustomerRegistry $customerRegistry
    ) {
        $this->earningCalculator = $earning;
        $this->storeManager = $storeManager;
        $this->integrateHelperData = $integrateHelperData;
        $this->customerFactory = $customerFactory;
        $this->scopeConfig = $scopeConfig;
        $this->customerRegistry = $customerRegistry;

        parent::__construct($objectManager);
    }

    /**
     * @param $data
     *
     * @return void
     */
    public function saveRPDataBeforeQuoteCollect($data)
    {
        if (isset($data['use_reward_point']) && $data['use_reward_point'] == true) {
            /** @var  \Magento\Quote\Model\Quote $quote */
            $quote = $this->getQuote();
            $quote->getShippingAddress()->setCollectShippingRates(true);

            $quote->setUseRewardPoints(true);
        }
    }

    /**
     * @return RewardPointQuoteData
     */
    public function getQuoteRPData()
    {
        // TODO: Implement getRPDataAfterQuoteCollect() method.
        $customerRPDetail = $this->getRewardCollection($this->getQuote()->getCustomer());

        $ratePoint = $customerRPDetail->getRateToPoints()->getPoints(true);
        $rateCurrency = $customerRPDetail->getRateToPoints()->getCurrencyAmount();

        $quoteRpData = new RewardPointQuoteData();
        $rpEarn = $this->earningCalculator->calculation($this->getQuote(), $rateCurrency, $ratePoint);

        $minPointsBalance = (int)$this->scopeConfig->getValue(
            \Magento\Reward\Model\Reward::XML_PATH_MIN_POINTS_BALANCE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->getQuote()->getStoreId()
        );

        $quoteRpData->addData(
            [
                'use_reward_point'                        => $this->getQuote()->getUseRewardPoints() === true,
                'customer_balance'                        => floatval($customerRPDetail->getPointsBalance()),
                'customer_balance_currency'               => floatval($customerRPDetail->getCurrencyAmount()),
                'customer_balance_base_currency'          => floatval($customerRPDetail->getCurrencyAmount()),
                'reward_point_spent'                      => $this->getQuote()->getData('reward_points_balance'),
                'reward_point_discount_amount'            => -($this->getQuote()->getData('reward_currency_amount')),
                'base_reward_point_discount_amount'       => -($this->getQuote()->getData('base_reward_currency_amount')),
                'reward_point_earn'                       => $rpEarn,
                'customer_reward_points_once_min_balance' => $minPointsBalance,
                'can_use_reward_points'                   => $minPointsBalance <= $customerRPDetail->getPointsBalance(),
            ]
        );

        return $quoteRpData;
    }

    /**
     * @param mixed $customerId
     * @param null  $scope
     *
     * @return \Magento\Reward\Model\Reward
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getRewardCollection($customerId, $scope = null)
    {
        if (is_null($scope)) {
            $scope = $this->storeManager->getWebsite()->getId();
        }
        if ($customerId && is_string($customerId)) {
            $customer = $this->customerFactory->create()->load($customerId);
        } else {
            $customer = $this->customerFactory->create()->load($customerId->getId());
        }
        if ($this->rewardInstance === null) {
            if ($customer && $customer->getId()) {
                $this->rewardInstance = $this->getRewardFactory()->create()
                    ->setCustomer($customer->getDataModel())
                    ->setWebsiteId($scope)
                    ->loadByCustomer();
            }
        }

        return $this->rewardInstance;
    }

    protected function getRewardFactory()
    {
        if (is_null($this->rewardFactory)) {
            $this->rewardFactory = $this->objectManager->get('Magento\Reward\Model\RewardFactory');
        }

        return $this->rewardFactory;
    }

    /**
     *
     * @param mixed $customer
     * @param null  $scope
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCurrentPointBalance($customer, $scope = null)
    {
        $customerRPDetail = $this->getRewardCollection($customer, $scope);

        return floatval($customerRPDetail->getPointsBalance());
    }

    /**
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @param $websiteId
     * @param $storeId
     * @param $amountDelta
     *
     * @return mixed|void
     * @throws LocalizedException
     */
    public function updateCustomerCurrentPointBalance($customer, $websiteId, $storeId, $amountDelta)
    {
        try {
            $data = [
                'store_id'                    => $storeId,
                'points_delta'                => $amountDelta,
                'comment'                     => 'Adjusted on ConnectPOS',
                'reward_update_notification'  => 1,
                'reward_warning_notification' => 1,
            ];
            $this->validatePointsDelta((string)$amountDelta);

            if (empty($storeId)) {
                if ($customer->getStoreId() == 0) {
                    $defaultStore = $this->storeManager->getDefaultStoreView();
                    if (!$defaultStore) {
                        $allStores = $this->storeManager->getStores();
                        if (isset($allStores[0])) {
                            $defaultStore = $allStores[0];
                        }
                    }
                    $data['store_id'] = $defaultStore ? $defaultStore->getStoreId() : null;
                } else {
                    $data['store_id'] = $customer->getStoreId();
                }
            }

            $reward = $this->objectManager->create('Magento\Reward\Model\Reward');
            $reward->setCustomer($customer)
                ->setWebsiteId($this->storeManager->getStore($data['store_id'])->getWebsiteId())
                ->loadByCustomer();

            $reward->addData($data);
            $reward->setAction(0)
                ->setActionEntity($customer)
                ->updateRewardPoints();
        } catch (\Exception $e) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/connectpos.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $logger->info('====> Unable to adjust reward points');
            $logger->info($e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    /**
     * Validates reward points delta value.
     *
     * @param string $pointsDelta
     *
     * @return void
     * @throws LocalizedException
     */
    private function validatePointsDelta(string $pointsDelta): void
    {
        if (filter_var($pointsDelta, FILTER_VALIDATE_INT) === false) {
            throw new LocalizedException(__('Reward points should be a valid integer number.'));
        }
    }

    /**
     * @inheritDoc
     */
    public function calculateRewardDiscount($customerId, $points, $websiteId)
    {
        // TODO: Implement calculateRewardDiscount() method.
    }
}
