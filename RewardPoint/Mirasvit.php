<?php

namespace SM\Integrate\RewardPoint;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use SM\Integrate\Data\RewardPointQuoteData;
use SM\Integrate\RewardPoint\Contract\AbstractRPIntegrate;
use SM\Integrate\RewardPoint\Contract\RPIntegrateInterface;

class Mirasvit extends AbstractRPIntegrate implements RPIntegrateInterface
{
    /**
     * @var ModuleManager
     */
    protected $moduleManager;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var RequestInterface
     */
    protected $request;

    public function __construct(
        ObjectManagerInterface $objectManager,
        ModuleManager $moduleManager,
        CartRepositoryInterface $quoteRepository,
        RequestInterface $request,
        Session $checkoutSession
    ) {
        $this->moduleManager = $moduleManager;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->request = $request;
        parent::__construct($objectManager);
    }

    /**
     * @inheritDoc
     */
    public function saveRPDataBeforeQuoteCollect($data)
    {
        $pointsAmount = $data['reward_point_spent'] ?? 0;
        $customerBalanceAmount = $this->getRewardsModel()->getBalance($this->getQuote()->getCustomerId());

        // TODO: convert points to amount
        $rpDiscountAmount = 0;

        if (isset($data['use_reward_point']) && $data['use_reward_point'] == true) {
            $this->getRewardsModel()->apply($this->getQuote()->getId(), $pointsAmount);

            $this->getQuote()->setData('reward_point_spent', $pointsAmount);
            $this->getQuote()->setData('use_reward_point', true);
            $this->getQuote()->setData('reward_currency_amount', $rpDiscountAmount);
            $this->getQuote()->setData('base_reward_currency_amount', $rpDiscountAmount);
        } else {
            $this->getQuote()->setData('use_reward_point', false);
        }

        $this->getQuote()->setData('customer_balance_currency', $customerBalanceAmount);
        $this->getQuote()->setData('customer_balance_base_currency', $customerBalanceAmount);
    }

    /**
     * @inheritDoc
     */
    public function getQuoteRPData()
    {
        // TODO: Implement getQuoteRPData() method.
    }

    /**
     * @inheritDoc
     */
    public function getCurrentPointBalance($customerId, $scope = null)
    {
        // TODO: Implement getCurrentPointBalance() method.
    }

    /**
     * @inheritDoc
     */
    public function updateCustomerCurrentPointBalance($customer, $websiteId, $storeId, $amountDelta)
    {
        // TODO: Implement updateCustomerCurrentPointBalance() method.
    }

    /**
     * @inheritDoc
     */
    public function calculateRewardDiscount($customerId, $points, $websiteId)
    {
        // TODO: Implement calculateRewardDiscount() method.
    }

    /**
     * @return \Mirasvit\RewardsCheckout\Model\Checkout\Rewards|mixed
     */
    protected function getRewardsModel()
    {
        return $this->objectManager->get('Mirasvit\RewardsCheckout\Model\Checkout\Rewards');
    }

    /**
     * @return \Mirasvit\Rewards\Helper\Checkout|mixed
     */
    protected function getRewardCheckout()
    {
        return $this->objectManager->get('Mirasvit\Rewards\Helper\Checkout');
    }

    /**
     * @return \Mirasvit\Rewards\Helper\Purchase|mixed
     */
    protected function getRewardPurchase()
    {
        return $this->objectManager->get('Mirasvit\Rewards\Helper\Purchase');
    }
}
