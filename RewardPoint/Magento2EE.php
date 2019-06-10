<?php
/**
 * Created by Hung Nguyen - hungnh@smartosc.com
 * Date: 02/10/2018
 * Time: 17:39
 */

namespace SM\Integrate\RewardPoint;

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
     * @var \SM\Integrate\RewardPoint\Magento2EE\Earning
     */
    private $earningCalculator;

    /**
     * Magento2EE constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface    $objectManager
     * @param \Magento\Store\Model\StoreManagerInterface   $storeManager
     * @param \SM\Integrate\Helper\Data                    $integrateHelperData
     * @param \SM\Integrate\RewardPoint\Magento2EE\Earning $earning
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        Data $integrateHelperData,
        Earning $earning
    ) {
        $this->earningCalculator   = $earning;
        $this->storeManager       = $storeManager;
        $this->integrateHelperData = $integrateHelperData;
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

        $ratePoint    = $customerRPDetail->getRateToPoints()->getPoints(true);
        $rateCurrency = $customerRPDetail->getRateToPoints()->getCurrencyAmount();

        $quoteRpData      = new RewardPointQuoteData();
        $rpEarn = $this->earningCalculator->calculation($this->getQuote(), $rateCurrency, $ratePoint);
        $quoteRpData->addData(
            [
                'use_reward_point'                  => $this->getQuote()->getUseRewardPoints() === true,
                'customer_balance'                  => floatval($customerRPDetail->getPointsBalance()),
                'customer_balance_currency'         => floatval($customerRPDetail->getCurrencyAmount()),
                'customer_balance_base_currency'    => floatval($customerRPDetail->getCurrencyAmount()),
                'reward_point_spent'                => $this->getQuote()->getData('reward_points_balance'),
                'reward_point_discount_amount'      => -($this->getQuote()->getData('reward_currency_amount')),
                'base_reward_point_discount_amount' => -($this->getQuote()->getData('base_reward_currency_amount')),
                'reward_point_earn'                 => $rpEarn,
                'customer_reward_points_once_min_balance' => ''
            ]
        );
        return $quoteRpData;
    }

    protected function getRewardCollection($customer, $scope = null)
    {
        if (is_null($scope)) {
            $scope = $this->storeManager->getWebsite()->getId();
        }
        if (is_null($this->rewardInstance)) {
            if ($customer && $customer->getId()) {
                $this->rewardInstance = $this->getRewardFactory()->create()->setCustomer(
                    $customer
                )->setWebsiteId(
                    $scope
                )->loadByCustomer();
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
     * @param      $customer
     * @param null $scope
     *
     * @return int
     */
    public function getCurrentPointBalance($customer, $scope = null)
    {
        $customerRPDetail = $this->getRewardCollection($customer, $scope);
        return floatval($customerRPDetail->getPointsBalance());
    }
}
