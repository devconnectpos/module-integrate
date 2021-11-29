<?php

namespace SM\Integrate\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;
use Magento\Config\Model\Config\Loader;

/**
 * Class StorePickUpIntegrateManagement
 * @package SM\Integrate\Model
 */
class StorePickUpIntegrateManagement extends ServiceAbstract
{

    /**
     * @var StorePickUpIntegrateInterface
     */
    protected $currentIntegrateModel;

    /**
     * @var array
     */
    public static $LIST_STORE_PICK_UP_INTEGRATE
        = [
            'mageworx' => [
                [
                    "version" => "~1.0.0",
                    "class"   => "SM\\Integrate\\StorePickUp\\MageWorx"
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
     * StorePickUpIntegrateManagement constructor.
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
     * @return mixed|StoreCreditIntegrateInterface
     */
    public function getCurrentIntegrateModel()
    {
        $config = $this->configLoader->getConfigByPath('xretail/pos', 'default', 0);
        $configIntegrateStorePickUpValue      = isset($config['xretail/pos/integrate_store_pick_up_extension']) ?
            $config['xretail/pos/integrate_store_pick_up_extension']['value'] : 'none';
        if (is_null($this->currentIntegrateModel)) {
            // FIXME: do something to get current integrate class
            if ($configIntegrateStorePickUpValue !== 'none') {
                if (!isset(self::$LIST_STORE_PICK_UP_INTEGRATE[$configIntegrateStorePickUpValue])) {
                    return $this->currentIntegrateModel;
                }

                $class = self::$LIST_STORE_PICK_UP_INTEGRATE[$configIntegrateStorePickUpValue][0]['class'];
            } else {
                $class = self::$LIST_STORE_PICK_UP_INTEGRATE['mageworx'][0]['class'];
            }

            $this->currentIntegrateModel = $this->objectManager->create($class);
        }

        return $this->currentIntegrateModel;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getList()
    {
        if (!$this->getCurrentIntegrateModel()) {
            return [];
        }

        return $this->getCurrentIntegrateModel()
            ->loadStoreLocationData($this->getSearchCriteria())
            ->getOutput();
    }
}
