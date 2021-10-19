<?php

namespace SM\Integrate\RewardPoint\Amasty;

use Magento\Customer\Model\SessionFactory as CustomerSessionFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote;

/**
 * Class Earning
 *
 * @package SM\Integrate\RewardPoint\Amasty
 */
class Earning
{
    const ORDER_COMPLETED_ACTION = 'ordercompleted';
    const MONEY_SPENT_ACTION = 'moneyspent';

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \Amasty\Rewards\Model\Repository\RuleRepository
     */
    private $ruleRepository;

    /**
     * @var CustomerSessionFactory
     */
    private $customerSessionFactory;

    /**
     * @var \Amasty\Rewards\Model\Calculation|\Amasty\Rewards\Model\Calculation\Earning
     */
    private $calculation;

    public function __construct(ObjectManagerInterface $objectManager,
        CustomerSessionFactory $customerSessionFactory
    ) {
        $this->objectManager = $objectManager;
        $this->customerSessionFactory = $customerSessionFactory;
        $this->ruleRepository = $objectManager->get('Amasty\Rewards\Model\Repository\RuleRepository');

        if (class_exists('Amasty\Rewards\Model\Calculation')) {
            $this->calculation = $objectManager->get('Amasty\Rewards\Model\Calculation');
        } elseif (class_exists('Amasty\Rewards\Model\Calculation\Earning')) {
            $this->calculation = $objectManager->get('Amasty\Rewards\Model\Calculation\Earning');
        }
    }

    /**
     * @param Quote $quote
     *
     * @return float
     */
    public function calculate($quote)
    {
        if (!$this->calculation) {
            return 0;
        }

        $amount = 0;
        $website = $quote->getStore()->getWebsiteId();
        $customerGroup = $quote->getCustomerGroupId() ?: $this->customerSessionFactory->create()->getCustomerGroupId();
        $rules = $this->ruleRepository->getRulesByAction(self::MONEY_SPENT_ACTION, $website, $customerGroup);

        if ($quote->isVirtual()) {
            $address = $quote->getBillingAddress();
        } else {
            $address = $quote->getShippingAddress();
        }

        /** @var \Amasty\Rewards\Model\Rule $rule */
        foreach ($rules as $rule) {
            if (!$rule->validate($address)) {
                continue;
            }

            if (method_exists($this->calculation, 'calculateSpentReward')) {
                $amount += $this->calculation->calculateSpentReward($address, $rule);
            } elseif (method_exists($this->calculation, 'calculateByAddress')) {
                $amount += $this->calculation->calculateByAddress($address, $rule);
            }
        }

        $rules = $this->ruleRepository->getRulesByAction(self::ORDER_COMPLETED_ACTION, $website, $customerGroup);

        /** @var \Amasty\Rewards\Model\Rule $rule */
        foreach ($rules as $rule) {
            if ($rule->validate($address)) {
                $amount += $rule->getAmount();
            }
        }

        return floatval(round($amount, 2));
    }
}
