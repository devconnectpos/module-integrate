<?php

namespace SM\Integrate\Warehouse\Plugin\Contract;

use SM\Performance\Helper\RealtimeManager;
use Magento\Framework\ObjectManagerInterface;


abstract class SourceAbstract
{
    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    protected $realtimeManager;
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * RealTimeTax constructor.
     *
     * @param \SM\Performance\Helper\RealtimeManager $realtimeManager
     * @param \Magento\Framework\ObjectManagerInterface        $objectManager
     */
    public function __construct(
        RealtimeManager $realtimeManager,
        ObjectManagerInterface $objectManager
    ) {
        $this->realtimeManager = $realtimeManager;
        $this->objectManager = $objectManager;
    }

    /**
     * @param $result
     * @param $skus
     * @return  mixed|void
     * @throws \Exception
     */
    protected function syncSource(
        $result,
        $skus
    ) {
        $ids = [];

        foreach ($skus as $sku) {
            $productId = $this->objectManager->get('Magento\Catalog\Model\Product')->getIdBySku($sku);
            array_push($ids,$productId);
        }
        $this->realtimeManager->trigger(
            RealtimeManager::PRODUCT_ENTITY,
            join(",", array_unique($ids)),
            RealtimeManager::TYPE_CHANGE_UPDATE
        );
        return $result;
    }
}
