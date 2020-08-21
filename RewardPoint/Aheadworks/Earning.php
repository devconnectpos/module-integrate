<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 7/28/17
 * Time: 10:36 AM
 */

namespace SM\Integrate\RewardPoint\Aheadworks;

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
        return $this->getEarningCalculator()->calculationByQuote($quote, $customerId, $websiteId)->getPoints();
    }

    public function calculationAmount(Quote $quote, $customerId, $websiteId = null)
    {
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

    protected function getEarningCalculator()
    {
        return $this->objectManager->get('Aheadworks\RewardPoints\Model\Calculator\Earning');
    }
}
