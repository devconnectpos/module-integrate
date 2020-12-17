<?php

namespace SM\Integrate\RewardPoint;

use Magento\Framework\Exception\LocalizedException;
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
        if (isset($data['use_reward_point']) && $data['use_reward_point'] == true) {
            $usedPoints = $data['reward_point_spent'] ?? 0;

            if (!$usedPoints || $usedPoints < 0) {
                throw new LocalizedException(__('Points "%1" not valid.', $usedPoints));
            }

            $minPoints = $this->getConfig()->getMinPointsRequirement($this->getQuote()->getStoreId());
            $pointsLeft = $this->getRewardRepository()->getCustomerRewardBalance($this->getQuote()->getCustomerId());

            if ($minPoints && $pointsLeft < $minPoints) {
                throw new LocalizedException(
                    __('You need at least %1 points to pay for the order with reward points.', $minPoints)
                );
            }

            if ($usedPoints > $pointsLeft) {
                throw new LocalizedException(__('Too much point(s) used.'));
            }

            $pointsData = $this->limitValidate($this->getQuote(), $usedPoints);
            $usedPoints = abs($pointsData['allowed_points']);
            $rpDiscountAmount = floatval($usedPoints / $this->getHelper()->getPointsRate());
            $customerBalanceAmount = floatval($pointsLeft / $this->getHelper()->getPointsRate());

            $this->collectCurrentTotals($this->getQuote(), $usedPoints);
            $this->getQuote()->setData('reward_point_spent', $usedPoints);
            $this->getQuote()->setData('use_reward_point', true);
            $this->getQuote()->setData('reward_currency_amount', $rpDiscountAmount);
            $this->getQuote()->setData('base_reward_currency_amount', $rpDiscountAmount);
            $this->getQuote()->setData('customer_balance_currency', $customerBalanceAmount);
            $this->getQuote()->setData('customer_balance_base_currency', $customerBalanceAmount);
            $this->getRewardQuote()->addData(
                [
                    'id'            => $this->getQuote()->getId(),
                    'quote_id'      => $this->getQuote()->getId(),
                    'reward_points' => $usedPoints,
                ]
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getQuoteRPData()
    {
        /** @var  \Magento\Quote\Model\Quote $quote */
        $quote = $this->getQuote();
        $minPointsBalance = (int)$this->getConfig()->getMinPointsRequirement($quote->getStoreId());
        $customerBalance = $this->getCurrentPointBalance($quote->getCustomerId());

        $quoteRpData = new RewardPointQuoteData();
        $useRewardPoints = $this->getQuote()->getData('use_reward_point') === true;
        $canUseRewardPoints = $customerBalance >= $minPointsBalance;

        $rpEarn = $this->earning->calculate($quote);

        $rpData = [
            'use_reward_point'                        => $useRewardPoints,
            'customer_balance'                        => floatval($customerBalance),
            'customer_balance_currency'               => $this->getQuote()->getData('customer_balance_currency'),
            'customer_balance_base_currency'          => $this->getQuote()->getData('customer_balance_base_currency'),
            'reward_point_spent'                      => $this->getQuote()->getData('reward_point_spent'),
            'reward_point_discount_amount'            => $this->getQuote()->getData('reward_currency_amount'),
            'base_reward_point_discount_amount'       => $this->getQuote()->getData('base_reward_currency_amount'),
            'reward_point_earn'                       => $rpEarn,
            'reward_point_earn_amount'                => floatval($rpEarn / $this->getHelper()->getPointsRate()),
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
     * {@inheritdoc}
     */
    private function collectCurrentTotals(\Magento\Quote\Model\Quote $quote, $usedPoints)
    {
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setData('amrewards_point', $usedPoints);
        $quote->setDataChanges(true);
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @param float                      $usedPoints
     *
     * @return array
     */
    private function limitValidate(\Magento\Quote\Model\Quote $quote, $usedPoints)
    {
        $pointsData['allowed_points'] = $usedPoints;
        $isEnableLimit = $this->getConfig()->isEnableLimit($quote->getStoreId());

        // Limit amount = 1, limit percent = 2
        if ($isEnableLimit == 1) {
            $limitAmount = $this->getConfig()->getRewardAmountLimit($quote->getStoreId());

            if ($usedPoints > $limitAmount) {
                $pointsData['allowed_points'] = $limitAmount;
            }
        } elseif ($isEnableLimit == 2) {
            $limitPercent = $this->getConfig()->getRewardPercentLimit($quote->getStoreId());
            $subtotal = $quote->getSubtotal();
            $allowedPercent = round(($subtotal / 100 * $limitPercent) / $quote->getBaseToQuoteRate(), 2);
            $rate = $this->getHelper()->getPointsRate();
            $basePoints = $usedPoints / $rate;

            if ($basePoints > $allowedPercent) {
                $pointsData['allowed_points'] = $allowedPercent * $rate;
            }
        }

        return $pointsData;
    }

    /**
     * @return mixed|\Amasty\Rewards\Model\Repository\RewardsRepository
     */
    private function getRewardRepository()
    {
        return $this->objectManager->get('Amasty\Rewards\Model\Repository\RewardsRepository');
    }

    /**
     * @return mixed|\Amasty\Rewards\Model\Quote
     */
    private function getRewardQuote()
    {
        return $this->objectManager->get('Amasty\Rewards\Model\Quote');
    }

    /**
     * @return mixed|\Amasty\Rewards\Model\Config
     */
    private function getConfig()
    {
        return $this->objectManager->get('Amasty\Rewards\Model\Config');
    }

    /**
     * @return mixed|\Amasty\Rewards\Helper\Data
     */
    private function getHelper()
    {
        return $this->objectManager->get('Amasty\Rewards\Helper\Data');
    }
}
