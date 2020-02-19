<?php

namespace SM\Integrate\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;
use Magento\Config\Model\Config\Loader;

/**
 * Class RMAIntegrateManagement
 * @package SM\Integrate\Model
 */
class RMAIntegrateManagement extends ServiceAbstract
{

    /**
     * @var RMAIntegrateInterface
     */
    protected $currentIntegrateModel;

    /**
     * @var array
     */
    public static $LIST_RMA_INTEGRATE
        = [
            'aheadWorks' => [
                [
                    "version" => "~1.0.0",
                    "class"   => "SM\\Integrate\\RMA\\AheadWorks"
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
     * RMAIntegrateManagement constructor.
     * @param RequestInterface $requestInterface
     * @param DataConfig $dataConfig
     * @param StoreManagerInterface $storeManager
     * @param ObjectManagerInterface $objectManager
     * @param Loader $loader
     */
    public function __construct(
        RequestInterface $requestInterface,
        DataConfig $dataConfig,
        StoreManagerInterface $storeManager,
        ObjectManagerInterface $objectManager,
        Loader $loader
    ) {
        $this->objectManager = $objectManager;
        $this->configLoader  = $loader;
        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    /**
     * @return mixed|RMAIntegrateInterface
     */
    public function getCurrentIntegrateModel()
    {
        $config = $this->configLoader->getConfigByPath('xretail/pos', 'default', 0);
        $configIntegrateRMAValue      = isset($config['xretail/pos/integrate_rma_extension']) ?
            $config['xretail/pos/integrate_rma_extension']['value'] : 'none';
        if (is_null($this->currentIntegrateModel)) {
            // FIXME: do something to get current integrate class
            if ($configIntegrateRMAValue !== 'none') {
                $class = self::$LIST_RMA_INTEGRATE[$configIntegrateRMAValue][0]['class'];
            } else {
                $class = self::$LIST_RMA_INTEGRATE['aheadWorks'][0]['class'];
            }

            $this->currentIntegrateModel = $this->objectManager->create($class);
        }

        return $this->currentIntegrateModel;
    }

    public function getOrderRMA() {
        return $this->getCurrentIntegrateModel()
            ->loadOrderRMAData($this->getSearchCriteria())
            ->getOutput();
    }

    public function getListCustomFields() {
        return $this->getCurrentIntegrateModel()
            ->loadListCustomFields($this->getSearchCriteria())
            ->getOutput();
    }

    public function createRequestRMA() {
        return $this->getCurrentIntegrateModel()
            ->createRequestRMA()
            ->getOutput();
    }
}
