<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 10/13/17
 * Time: 3:02 PM
 */

namespace SM\Integrate\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\Core\Api\Data\AwCodePool;
use SM\Core\Api\Data\AwCodePool\AwGcCode;
use SM\Integrate\Helper\Data as IntegrateHelper;
use SM\Integrate\RewardPoint\Contract\RPIntegrateInterface;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;
use Magento\Config\Model\Config\Loader;

class GCIntegrateManagement extends ServiceAbstract
{

    /**
     * @var RPIntegrateInterface
     */
    protected $currentIntegrateModel;

    /**
     * @var array
     */
    public static $LIST_GC_INTEGRATE
        = [
            'aheadWorks' => [
                [
                    "version" => "~1.2.1",
                    "class"   => "SM\\Integrate\\GiftCard\\AheadWorks121"
                ]
            ],
            'mage2_ee'   => [
                [
                    "version" => "~2.1.7",
                    "class"   => "SM\\Integrate\\GiftCard\\Magento2EE"
                ]
            ],
        ];
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;
    /**
     * @var \Magento\Config\Model\Config\Loader
     */
    protected $configLoader;
    /**
     * @var IntegrateHelper
     */
    private $integrateHelper;

    /**
     * GCIntegrateManagement constructor.
     *
     * @param \Magento\Framework\App\RequestInterface $requestInterface
     * @param \SM\XRetail\Helper\DataConfig $dataConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Config\Model\Config\Loader $loader
     * @param IntegrateHelper $integrateHelper
     */
    public function __construct(
        RequestInterface $requestInterface,
        DataConfig $dataConfig,
        StoreManagerInterface $storeManager,
        ObjectManagerInterface $objectManager,
        Loader $loader,
        IntegrateHelper $integrateHelper
    ) {
        $this->objectManager = $objectManager;
        $this->configLoader  = $loader;
        $this->integrateHelper = $integrateHelper;
        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    /**
     * @return \SM\Integrate\GiftCard\Contract\GCIntegrateInterface
     */
    public function getCurrentIntegrateModel()
    {
        $config = $this->configLoader->getConfigByPath('xretail/pos', 'default', 0);
        $configIntegrateGiftCardValue      = isset($config['xretail/pos/integrate_gc']) ?
            $config['xretail/pos/integrate_gc']['value'] : 'none';

        $configPWA = $this->configLoader->getConfigByPath('pwa/integrate', 'default', 0);
        $configIntegrateGiftCardValueInPWA      = isset($configPWA['pwa/integrate/pwa_integrate_gift_card']) ?
            $configPWA['pwa/integrate/pwa_integrate_gift_card']['value'] : 'none';

        if (is_null($this->currentIntegrateModel) && $configIntegrateGiftCardValue != 'none') {
            // FIXME: do something to get current integrate class
            $class = self::$LIST_GC_INTEGRATE[$configIntegrateGiftCardValue][0]['class'];

            $this->currentIntegrateModel = $this->objectManager->create($class);
        } elseif (is_null($this->currentIntegrateModel) && $configIntegrateGiftCardValueInPWA != 'none') {
            $class = self::$LIST_GC_INTEGRATE['aheadWorks'][0]['class'];

            $this->currentIntegrateModel = $this->objectManager->create($class);
        }

        return $this->currentIntegrateModel;
    }

    /**
     * @param $data
     *
     * @return void
     */
    public function saveGCDataBeforeQuoteCollect($data)
    {
        $this->getCurrentIntegrateModel()->saveGCDataBeforeQuoteCollect($data);
    }

    public function updateRefundToGCProduct($data)
    {
        if ($m = $this->getCurrentIntegrateModel()) {
            $m->updateRefundToGCProduct($data);
        }
    }

    public function getRefundToGCProductId()
    {
        return $this->getCurrentIntegrateModel()->getRefundToGCProductId();
    }

    public function getGCCodePool()
    {
        $class = self::$LIST_GC_INTEGRATE['aheadWorks'][0]['class'];
        if ($this->integrateHelper->isAHWGiftCardExist()) {
            return $this->objectManager->create($class)->getGCCodePool();
        }

        return [];
    }

    public function getQuoteGCData()
    {
        return $this->getCurrentIntegrateModel()->getQuoteGCData();
    }


    /**
     * CPOS API function to get AheadWorks Gift Card code pools
     * @return mixed
     * @throws \ReflectionException
     */
    public function getAwGiftCardCodePools()
    {
        if (!$this->integrateHelper->isAHWGiftCardExist()) {
            return $this->getSearchResult()
                ->setItems([])
                ->setSearchCriteria($this->getSearchCriteria())
                ->getOutput();
        }


        $searchCriteria = $this->getSearchCriteria();
        /** @var \Aheadworks\Giftcard\Model\ResourceModel\Pool\Collection $collection */
        $collection   = $this->objectManager->create('Aheadworks\Giftcard\Model\ResourceModel\Pool\Collection');
        $collection->setCurPage($searchCriteria->getData('currentPage'));
        $collection->setPageSize($searchCriteria->getData('pageSize'));

        if ($collection->getLastPageNumber() < (int)$searchCriteria->getData('currentPage')) {
            return $this->getSearchResult()
                ->setItems([])
                ->setSearchCriteria($this->getSearchCriteria())
                ->getOutput();
        }

        $codePools = [];
        foreach ($collection as $item) {
            $codePools[] = $item->getData();
        }

        $results = [];
        foreach ($codePools as $codePool) {
            $pool = new AwCodePool();
            $pool->addData($codePool);

            /** @var \AheadWorks\Giftcard\Model\ResourceModel\Pool\Code\Collection $codeCollection */
            $codeCollection = $this->objectManager->create('\Aheadworks\Giftcard\Model\ResourceModel\Pool\Code\Collection');
            $codeCollection->addFieldToFilter('pool_id', ['eq' => $pool->getId()]);

            if ($codeCollection->count() === 0) {
                continue;
            }

            $codes = [];
            foreach ($codeCollection->getItems() as $item) {
                $code = new AwGcCode();
                $code->addData($item->getData());
                $codes[] = $code->getOutput();
            }

            $pool->setCodes($codes);
            $results[] = $pool;
        }

        return $this->getSearchResult()
            ->setItems($results)
            ->setSearchCriteria($this->getSearchCriteria())
            ->getOutput();
    }
}
