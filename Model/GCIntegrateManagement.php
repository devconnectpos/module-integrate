<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 10/13/17
 * Time: 3:02 PM
 */

namespace SM\Integrate\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use SM\Core\Api\Data\AwCodePool;
use SM\Core\Api\Data\AwCodePool\AwGcCode;
use SM\Integrate\Helper\Data as IntegrateHelper;
use SM\Integrate\GiftCard\Contract\GCIntegrateInterface;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;
use Magento\Config\Model\Config\Loader;
use SM\Integrate\GiftCard\Magento2EE;
use SM\Integrate\GiftCard\Amasty;
use SM\Integrate\GiftCard\AheadWorks121;

class GCIntegrateManagement extends ServiceAbstract
{

    /**
     * @var GCIntegrateInterface
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
                    "class"   => AheadWorks121::class,
                ],
            ],
            'amasty'     => [
                [
                    "version" => "~2.5.4",
                    "class"   => Amasty::class,
                ],
            ],
            'mage2_ee'   => [
                [
                    "version" => "~2.1.7",
                    "class"   => Magento2EE::class,
                ],
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
     * @var Json
     */
    private $jsonSerializer;

    /**
     * GCIntegrateManagement constructor.
     *
     * @param \Magento\Framework\App\RequestInterface    $requestInterface
     * @param \SM\XRetail\Helper\DataConfig              $dataConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\ObjectManagerInterface  $objectManager
     * @param \Magento\Config\Model\Config\Loader        $loader
     * @param IntegrateHelper                            $integrateHelper
     */
    public function __construct(
        RequestInterface $requestInterface,
        DataConfig $dataConfig,
        StoreManagerInterface $storeManager,
        ObjectManagerInterface $objectManager,
        Loader $loader,
        IntegrateHelper $integrateHelper,
        Json $jsonSerializer
    ) {
        $this->objectManager = $objectManager;
        $this->configLoader = $loader;
        $this->integrateHelper = $integrateHelper;
        $this->jsonSerializer = $jsonSerializer;
        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    /**
     * @return \SM\Integrate\GiftCard\Contract\GCIntegrateInterface
     */
    public function getCurrentIntegrateModel()
    {
        $config = $this->configLoader->getConfigByPath('xretail/pos', 'default', 0);
        $configIntegrateGiftCardValue = isset($config['xretail/pos/integrate_gc']) ?
            $config['xretail/pos/integrate_gc']['value'] : 'none';

        $configPWA = $this->configLoader->getConfigByPath('pwa/integrate', 'default', 0);
        $configIntegrateGiftCardValueInPWA = isset($configPWA['pwa/integrate/pwa_integrate_gift_card']) ?
            $configPWA['pwa/integrate/pwa_integrate_gift_card']['value'] : 'none';

        if (!is_null($this->currentIntegrateModel)) {
            return $this->currentIntegrateModel;
        }

        if ($configIntegrateGiftCardValue !== 'none') {
            // FIXME: do something to get current integrate class
            $class = self::$LIST_GC_INTEGRATE[$configIntegrateGiftCardValue][0]['class'];

            $this->currentIntegrateModel = $this->objectManager->create($class);
        } elseif ($configIntegrateGiftCardValueInPWA !== 'none') {
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
     *
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
        $collection = $this->objectManager->create('Aheadworks\Giftcard\Model\ResourceModel\Pool\Collection');
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

    /**
     * @return array
     * @throws \ReflectionException
     */
    public function getAmastyGiftCardCodePools()
    {
        if (!$this->integrateHelper->isUsingAmastyGiftCard()) {
            return $this->getSearchResult()
                ->setItems([])
                ->setSearchCriteria($this->getSearchCriteria())
                ->getOutput();
        }

        $codePools = $this->getAmastyCodePoolRepository()->getList();
        $results = [];

        foreach ($codePools as $codePool) {
            $rule = $this->getAmastyCodePoolRepository()->getRuleByCodePoolId($codePool->getCodePoolId());
            $codePoolRule = null;

            if (!is_null($rule)) {
                $codePoolRule = [
                    'id'         => $rule->getRuleId(),
                    'pool_id'    => $rule->getCodePoolId(),
                    'conditions' => $this->jsonSerializer->unserialize($rule->getConditionsSerialized()),
                ];
            }

            $results[] = [
                'id'       => $codePool->getCodePoolId(),
                'name'     => $codePool->getTitle(),
                'template' => $codePool->getTemplate(),
                'rule'     => $codePoolRule,
                'codes'    => $this->getAmastyCodesByCodePoolId($codePool->getCodePoolId()),
            ];
        }

        return $this->getSearchResult()
            ->setItems($results)
            ->setSearchCriteria($this->getSearchCriteria())
            ->getOutput();
    }

    /**
     * @return \Amasty\GiftCard\Api\CodePoolRepositoryInterface|mixed
     */
    protected function getAmastyCodePoolRepository()
    {
        return $this->objectManager->get('Amasty\GiftCard\Api\CodePoolRepositoryInterface');
    }

    /**
     * @param $id
     *
     * @return array
     */
    protected function getAmastyCodesByCodePoolId($id)
    {
        $collection = $this->objectManager->get('Amasty\GiftCard\Model\Code\ResourceModel\Collection');
        $results = [];

        if (!$collection) {
            return $results;
        }

        $items = $collection->addFieldToFilter('code_pool_id', $id)->getItems();

        foreach ($items as $item) {
            $results[] = [
                'code'    => $item->getCode(),
                'id'      => $item->getCodeId(),
                'pool_id' => $item->getCodePoolId(),
                'used'    => $item->getStatus(),
            ];
        }

        return $results;
    }
}
