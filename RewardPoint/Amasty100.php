<?php

namespace SM\Integrate\RewardPoint;

use Magento\Checkout\Model\Session;
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
    const POINTS_EARN = 'am_earn_reward_points';
    const POINTS_SPENT = 'am_spent_reward_points';

    /**
     * @var Amasty\Earning
     */
    private $earning;

    /**
     * @var \Magento\Framework\Module\Manager
     */
    private $moduleManager;

    /**
     * @var Session
     */
    protected $checkoutSession;

    public function __construct(
        ObjectManagerInterface $objectManager,
        \Magento\Framework\Module\Manager $moduleManager,
        Session $checkoutSession,
        \SM\Integrate\RewardPoint\Amasty\Earning $earning
    ) {
        $this->earning = $earning;
        $this->moduleManager = $moduleManager;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($objectManager);
    }

    /**
     * @inheritDoc
     * @throws NoSuchEntityException
     */
    public function saveRPDataBeforeQuoteCollect($data)
    {
        $pointsLeft = $this->getRewardRepository()->getCustomerRewardBalance($this->getQuote()->getCustomerId());
        $customerBalanceAmount = floatval($pointsLeft / $this->getHelper()->getPointsRate());

        if (isset($data['use_reward_point']) && $data['use_reward_point'] == true) {
            $usedPoints = $data['reward_point_spent'] ?? 0;

            if (!$usedPoints || $usedPoints < 0) {
                throw new LocalizedException(__('Points "%1" not valid.', $usedPoints));
            }

            $minPoints = $this->getConfig()->getMinPointsRequirement($this->getQuote()->getStoreId());

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
        } else {
            $this->getQuote()->setData('use_reward_point', false);
            $this->getQuote()->setData('customer_balance_currency', $customerBalanceAmount);
            $this->getQuote()->setData('customer_balance_base_currency', $customerBalanceAmount);
        }

        // XRT-6092: Fix issue with AddOn modules
        if ($this->getQuote()->getId() && $this->moduleManager->isEnabled('SmartOSC_Rewards') && !$this->checkoutSession->getQuoteId()) {
            $this->getQuote()->setIsActive(true); // Must make this quote active in order to get pass reward calculation
            $this->checkoutSession->setQuoteId($this->getQuote()->getId());
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

    public function updateCustomerCurrentPointBalance($customer, $websiteId, $storeId, $amountDelta)
    {
    }

    /**
     * {@inheritdoc}
     */
    private function collectCurrentTotals(\Magento\Quote\Model\Quote $quote, $usedPoints)
    {
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setData('amrewards_point', $usedPoints);
        $quote->setData(self::POINTS_SPENT, $usedPoints);
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

    /**
     * @return \Amasty\Rewards\Model\Calculation\Discount|mixed
     */
    private function getDiscountModel()
    {
        return $this->objectManager->get('Amasty\Rewards\Model\Calculation\Discount');
    }

    /**
     * @inheritDoc
     */
    public function calculateRewardDiscount($customerId, $points, $websiteId)
    {
        // TODO: Add logic for calculate discount if necessary
        $calculator = $this->getDiscountModel();
    }
}
