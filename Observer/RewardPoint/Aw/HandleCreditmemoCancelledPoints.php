<?php
declare(strict_types=1);

namespace SM\Integrate\Observer\RewardPoint\Aw;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class HandleCreditmemoCancelledPoints
 * @package SM\Integrate\Observer\RewardPoint\Aw
 */
class HandleCreditmemoCancelledPoints implements ObserverInterface
{
    /**
     * @var \Aheadworks\RewardPoints\Model\Calculator\Earning
     */
    private $earningCalculator;

    /**
     * @var \Aheadworks\RewardPoints\Model\Service\CustomerRewardPointsService
     */
    private $rewardPointsService;

    /**
     * @var \Aheadworks\RewardPoints\Model\Calculator\RateCalculator
     */
    private $rateCalculator;

    /**
     * @var \Aheadworks\RewardPoints\Model\Calculator\Earning\EarnItemsResolver
     */
    private $earnItemsResolver;

    /**
     * @var \Aheadworks\RewardPoints\Model\EarnRule\Applier
     */
    private $ruleApplier;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Aheadworks\RewardPoints\Model\Config
     */
    private $config;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ObjectManagerInterface $objectManager
     * @param ModuleManager $moduleManager
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ObjectManagerInterface $objectManager,
        ModuleManager $moduleManager
    ) {
        $this->objectManager = $objectManager;
        $this->storeManager = $storeManager;
        $this->moduleManager = $moduleManager;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        if (!$this->moduleManager->isEnabled('Aheadworks_RewardPoints')) {
            return $this;
        }

        $this->init();

        /** @var Creditmemo $creditmemo */
        $creditmemo = $observer->getData('creditmemo');
        $order = $creditmemo->getOrder();

        if (!$this->config->isCancelEarnedPointsRefundOrder($order->getStore()->getWebsiteId())) {
            return $this;
        }

        $totalOrderedQty = $order->getTotalQtyOrdered();
        $totalRefundedQty = 0;

        foreach ($creditmemo->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();

            if ($orderItem->isDummy() || $orderItem->getQtyInvoiced() <= 0) {
                continue;
            }

            $totalRefundedQty += $item->getQty();
        }

        if ($totalRefundedQty == 0) {
            return $this;
        }

        $customerId = $creditmemo->getCustomerId();
        $websiteId = $this->storeManager->getStore($creditmemo->getStoreId())->getWebsiteId();

        $rate = $this->rateCalculator->getSpendRate($websiteId);
        $cancelledPoints = $order->getData('reward_points_earned');

        if ($totalRefundedQty < $totalOrderedQty) {
            $cancelledPoints = $this->rateCalculator->calculateEarnPoints($customerId, $creditmemo->getGrandTotal(), $websiteId);
        }
        $cancelledPointsAmount = $this->rateCalculator->calculateRewardDiscount(
            $customerId,
            $cancelledPoints,
            $websiteId,
            $rate
        );

        $customerBalance = $this->rewardPointsService->getCustomerRewardPointsBalance(
            $order->getCustomerId(),
            $order->getStore()->getWebsiteId()
        );

        $residualPoints = 0;
        $residualPointsAmount = 0;

        if ($customerBalance < $cancelledPoints) {
            $residualPoints = abs($customerBalance - $cancelledPoints);
            $residualPointsAmount = $this->rateCalculator->calculateRewardDiscount(
                $customerId,
                $residualPoints,
                $websiteId,
                $rate
            );
        }

        $creditmemo->setBaseAwRewardPointsCancelled($cancelledPointsAmount);
        $creditmemo->setAwRewardPointsCancelled($cancelledPointsAmount);
        $creditmemo->setAwRewardPointsBlnceCancelled($cancelledPoints);

        $creditmemo->setBaseAwRewardPointsResidual($residualPointsAmount);
        $creditmemo->setAwRewardPointsResidual($residualPointsAmount);
        $creditmemo->setAwRewardPointsBlnceResidual($residualPoints);

        if ($customerBalance < $cancelledPoints) {
            $creditmemo->setAdjustment(-$residualPointsAmount);
            $creditmemo->setAdjustmentNegative($residualPointsAmount);
        } else {
            $creditmemo->setAdjustment(0);
            $creditmemo->setAdjustmentNegative(0);
        }

        foreach ($creditmemo->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();

            if ($orderItem->isDummy()) {
                continue;
            }

            if ($creditmemo->getGrandTotal() == 0) {
                $ratio = 0;
            } else {
                $ratio = (float)($item->getRowTotalInclTax() / $creditmemo->getGrandTotal());
            }

            $pts = round($cancelledPoints * $ratio);
            $ptsAmount = round($cancelledPointsAmount * $ratio, 2);
            $resPts = round($residualPoints * $ratio);
            $resPtsAmount = round($residualPointsAmount * $ratio, 2);

            $item->setAwRewardPointsCancelled($ptsAmount);
            $item->setBaseAwRewardPointsCancelled($ptsAmount);
            $item->setAwRewardPointsBlnceCancelled($pts);

            $item->setAwRewardPointsResidual($resPtsAmount);
            $item->setBaseAwRewardPointsResidual($resPtsAmount);
            $item->setAwRewardPointsBlnceResidual($resPts);
        }

        return $this;
    }

    /**
     * Initialize AheadWorks Reward Points classes
     */
    private function init()
    {
        $this->earningCalculator = $this->objectManager->get('Aheadworks\RewardPoints\Model\Calculator\Earning');
        $this->rewardPointsService = $this->objectManager->get('Aheadworks\RewardPoints\Model\Service\CustomerRewardPointsService');
        $this->rateCalculator = $this->objectManager->get('Aheadworks\RewardPoints\Model\Calculator\RateCalculator');
        $this->earnItemsResolver = $this->objectManager->get('Aheadworks\RewardPoints\Model\Calculator\Earning\EarnItemsResolver');
        $this->ruleApplier = $this->objectManager->get('Aheadworks\RewardPoints\Model\EarnRule\Applier');
        $this->config = $this->objectManager->get('Aheadworks\RewardPoints\Model\Config');
    }
}
