<?php

namespace SM\Integrate\GiftCard\Plugin;

use Magento\Framework\ObjectManagerInterface;

class SetGiftCardCode
{
    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;

    /**
     * @var \Magento\Framework\Api\SortOrderBuilder
     */
    private $sortOrderBuilder;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \Aheadworks\Giftcard\Api\PoolCodeRepositoryInterface
     */
    private $poolCodeRepository;

    /**
     * @var \Magento\Framework\EntityManager\EntityManager
     */
    private $entityManager;

    /**
     * SetGiftCardCode constructor.
     *
     * @param \Magento\Framework\Registry                    $registry
     * @param \Magento\Framework\Api\SortOrderBuilder        $sortOrderBuilder
     * @param \Magento\Framework\Api\SearchCriteriaBuilder   $searchCriteriaBuilder
     * @param \Magento\Framework\EntityManager\EntityManager $entityManager
     * @param ObjectManagerInterface                         $objectManager
     */
    public function __construct(
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\EntityManager\EntityManager $entityManager,
        ObjectManagerInterface $objectManager
    ) {
        $this->registry = $registry;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->entityManager = $entityManager;
        $this->poolCodeRepository = $objectManager->get('Aheadworks\Giftcard\Api\PoolCodeRepositoryInterface');
    }

    /**
     * @param \Aheadworks\Giftcard\Model\Service\PoolService $subject
     * @param \Closure $proceed
     * @param $poolId
     * @param bool $generateNew
     * @return mixed|string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundPullCodeFromPool(\Aheadworks\Giftcard\Model\Service\PoolService $subject, \Closure $proceed, $poolId, $generateNew = true)
    {
        $codes = $this->registry->registry('aw_gc_code');
        if (!$codes || $codes->isEmpty()) {
            return $proceed($poolId, $generateNew);
        }

        $code = $codes->dequeue();
        $sortOrder = $this->sortOrderBuilder
            ->setField(\Aheadworks\Giftcard\Api\Data\Pool\CodeInterface::ID)
            ->setDirection(\Magento\Framework\Api\SortOrder::SORT_ASC)
            ->create();
        $this->searchCriteriaBuilder
            ->addFilter(\Aheadworks\Giftcard\Api\Data\Pool\CodeInterface::USED, false)
            ->addFilter(\Aheadworks\Giftcard\Api\Data\Pool\CodeInterface::POOL_ID, $poolId)
            ->addFilter(\Aheadworks\Giftcard\Api\Data\Pool\CodeInterface::CODE, $code)
            ->setCurrentPage(1)
            ->setPageSize(1)
            ->addSortOrder($sortOrder);
        $poolCodes = $this->poolCodeRepository
            ->getList($this->searchCriteriaBuilder->create())
            ->getItems();

        if (!$poolCodes) {
            return null;
        }

        $poolCode = array_shift($poolCodes);
        $poolCode->setUsed(\Aheadworks\Giftcard\Model\Source\YesNo::YES);
        $this->entityManager->save($poolCode);

        return $poolCode->getCode();
    }
}
