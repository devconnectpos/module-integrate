<?php

namespace SM\Integrate\RewardPoint;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use SM\Integrate\Data\RewardPointQuoteData;
use SM\Integrate\RewardPoint\Contract\AbstractRPIntegrate;
use SM\Integrate\RewardPoint\Contract\RPIntegrateInterface;

/**
 * Class Amasty100
 *
 * @package SM\Integrate\RewardPoint
 */
class Amasty100 extends AbstractRPIntegrate implements RPIntegrateInterface
{
    /**
     * @var Amasty\Earning
     */
    private $earning;

    public function __construct(
        ObjectManagerInterface $objectManager,
        \SM\Integrate\RewardPoint\Amasty\Earning $earning
    ) {
        $this->earning = $earning;
        parent::__construct($objectManager);
    }

    /**
     * @inheritDoc
     * @throws NoSuchEntityException
     */
    public function saveRPDataBeforeQuoteCollect($data)
    {
        if (isset($data['use_reward_point'])&& $data['use_reward_point'] == true) {
            /** @var  \Magento\Quote\Model\Quote $quote */
            $quote = $this->getQuote();

            if (!$quote->getCustomerId()
                || !$this->getCurrentPointBalance($quote->getCustomerId())
            ) {
                throw new NoSuchEntityException(__('No reward points to be used'));
            }

            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->setUseRewardPoints(true);
        }
    }

    /**
     * @inheritDoc
     */
    public function getQuoteRPData()
    {
        /** @var  \Magento\Quote\Model\Quote $quote */
        $quote = $this->getQuote();
        $minPointsBalance = $this->getConfig()->getMinPointsRequirement($quote->getStoreId());
        $customerBalance = $this->getCurrentPointBalance($quote->getCustomerId());

        $quoteRpData = new RewardPointQuoteData();
        $useRewardPoints = $this->getQuote()->getUseRewardPoints() === true;
        $canUseRewardPoints = $minPointsBalance && $customerBalance >= $minPointsBalance;

        $rpEarn = $this->earning->calculate($quote);

        $rpData = [
            'use_reward_point'                        => $useRewardPoints,
            'customer_balance'                        => floatval($customerBalance),
            'customer_balance_currency'               => floatval($quote->getGrandTotal()),
            'customer_balance_base_currency'          => floatval($quote->getBaseGrandTotal()),
            'reward_point_spent'                      => $this->getQuote()->getData('reward_points_balance'),
            'reward_point_discount_amount'            => $this->getQuote()->getData('reward_currency_amount'),
            'base_reward_point_discount_amount'       => $this->getQuote()->getData('base_reward_currency_amount'),
            'reward_point_earn'                       => $rpEarn,
            'customer_reward_points_once_min_balance' => $minPointsBalance,
            'can_use_reward_points'                   => $canUseRewardPoints,
        ];

        $quoteRpData->addData($rpData);

        return $quoteRpData;
    }

    /**
     * @inheritDoc
     */
    public function getCurrentPointBalance($customerId, $scope = null)
    {
        return $this->getRewardRepository()->getCustomerRewardBalance($customerId);
    }

    /**
     * @return mixed|\Amasty\Rewards\Model\Repository\RewardsRepository
     */
    private function getRewardRepository()
    {
        return $this->objectManager->get('Amasty\Rewards\Model\Repository\RewardsRepository');
    }

    /**
     * @return mixed|\Amasty\Rewards\Model\Config
     */
    private function getConfig()
    {
        return $this->objectManager->get('Amasty\Rewards\Model\Config');
    }
}