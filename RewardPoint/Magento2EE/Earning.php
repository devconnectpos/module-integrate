<?php
/**
 * Created by Hung Nguyen - hungnh@smartosc.com
 * Date: 3/10/18
 * Time: 15:24
 */

namespace SM\Integrate\RewardPoint\Magento2EE;

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

    public function calculation(Quote $quote, $rateCurrency = null, $ratePoint = null)
    {
        if ($rateCurrency === null || $ratePoint === null) {
            return 0;
        }

        if (is_null($quote->getData('retail_discount_per_item'))) {
            $baseSubTotal = $quote->getBaseSubtotalWithDiscount()
                            - $quote->getData('base_reward_currency_amount')
                            + (0);
        } else {
            $baseSubTotal = $quote->getBaseSubtotalWithDiscount()
                            - $quote->getData('base_reward_currency_amount')
                            + ($quote->getData('retail_discount_per_item'));
        }

        if ($baseSubTotal <= 0) {
            return 0;
        }

        // if order use store credit, then baseSubTotal must subtract the store credit amount
        if ($quote->getData('customer_balance_amount_used')) {
            $baseSubTotal -= $quote->getData('customer_balance_amount_used');
        }

        return $this->calculateEarnPoints($baseSubTotal, $rateCurrency, $ratePoint);
    }

    /**
     * Calculate earn points
     *
     * @param float $amount
     * @param       $rateCurrency
     * @param       $ratePoint
     *
     * @return int
     */
    public function calculateEarnPoints($amount, $rateCurrency, $ratePoint)
    {
        return (int)$this->calculateRate($rateCurrency, $ratePoint, $amount);
    }

    /**
     * Calculate rate
     *  $result = ($baseY * $targetX) / $baseX
     *
     * @param int $baseX
     * @param int $baseY
     * @param int $targetX
     * @return float
     */
    private function calculateRate($baseX, $baseY, $targetX)
    {
        $result = 0;
        if ($baseX > 0) {
            $result = ($baseY * $targetX) / $baseX;
        }
        return $result;
    }
}
