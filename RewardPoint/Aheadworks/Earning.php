<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 7/28/17
 * Time: 10:36 AM
 */

namespace SM\Integrate\RewardPoint\Aheadworks;

use Aheadworks\RewardPoints\Model\Source\Calculation\PointsEarning;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote;

class Earning
{

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function calculation(Quote $quote, $customerId, $websiteId = null)
    {
        $baseSubTotal     = 0;
        $shippingDiscount = $quote->getBaseShippingDiscountAmount()
                            + $quote->getBaseAwRewardPointsShippingAmount();

        switch ($this->getConfigAheadworks()->getPointsEarningCalculation($websiteId)) {
            case PointsEarning::BEFORE_TAX:
                $baseSubTotal = $quote->getBaseGrandTotal()
                                - $quote->getBaseShippingAmount()
                                + $shippingDiscount
                                - $quote->getBaseTaxAmount();
                break;
            case PointsEarning::AFTER_TAX:
                $baseSubTotal = $quote->getBaseGrandTotal()
                                - $quote->getBaseShippingAmount()
                                + $shippingDiscount;
                break;
        }
        if ($baseSubTotal <= 0) {
            return 0;
        }

        // if order use store credit, then baseSubTotal must subtract the store credit amount
        if ($quote->getData('customer_balance_amount_used')) {
            $baseSubTotal -= $quote->getData('customer_balance_amount_used');
        }

        return $this->getRateCalculator()->calculateEarnPoints($customerId, $baseSubTotal, $websiteId);
    }

    public function calculationAmount(Quote $quote, $customerId, $websiteId = null) {
        return $this->getRateCalculator()->calculateRewardDiscount($customerId, $this->calculation($quote, $customerId, $websiteId), $websiteId);
    }

    /**
     * @return \Aheadworks\RewardPoints\Model\Calculator\RateCalculator
     */
    public function getRateCalculator()
    {
        return $this->objectManager->get('Aheadworks\RewardPoints\Model\Calculator\RateCalculator');
    }

    /**
     * @return \Aheadworks\RewardPoints\Model\Config
     */
    protected function getConfigAheadworks()
    {
        return $this->objectManager->get('Aheadworks\RewardPoints\Model\Config');
    }
}
