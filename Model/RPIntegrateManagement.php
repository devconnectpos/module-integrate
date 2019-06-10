<?php
/**
 * Created by IntelliJ IDEA.
 * User: vjcspy
 * Date: 20/03/2017
 * Time: 17:55
 */

namespace SM\Integrate\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\Integrate\RewardPoint\Contract\RPIntegrateInterface;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;

/**
 * Class RPIntegrateManagement
 *
 * @package SM\Integrate\Model
 */
class RPIntegrateManagement extends ServiceAbstract
{

    /**
     * @var RPIntegrateInterface
     */
    protected $currentIntegrateModel;

    /**
     * @var array
     */
    public static $LIST_RP_INTEGRATE
        = [
            'aheadWorks' => [
                [
                    "version" => "~1.0.0",
                    "class"   => "SM\\Integrate\\RewardPoint\\AheadWorks100"
                ]
            ],
            'mage2_ee' => [
                [
                    "version" => "~1.0.0",
                    "class"   => "SM\\Integrate\\RewardPoint\\Magento2EE"
                ]
            ],
            'mage_store'  => [
                [
                    'version' => "~1.0.0",
                    "class"   => "SM\\Integrate\\RewardPoint\\MageStore100"
                ]
            ],
        ];
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * RPIntegrateManagement constructor.
     *
     * @param \Magento\Framework\App\RequestInterface            $requestInterface
     * @param \SM\XRetail\Helper\DataConfig                      $dataConfig
     * @param \Magento\Store\Model\StoreManagerInterface         $storeManager
     * @param \Magento\Framework\ObjectManagerInterface          $objectManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        RequestInterface $requestInterface,
        DataConfig $dataConfig,
        StoreManagerInterface $storeManager,
        ObjectManagerInterface $objectManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->objectManager = $objectManager;
        $this->scopeConfig   = $scopeConfig;
        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    /**
     * @return \SM\Integrate\RewardPoint\Contract\RPIntegrateInterface
     */
    public function getCurrentIntegrateModel()
    {
        $configIntegrateRpValue = $this->scopeConfig->getValue('xretail/pos/integrate_rp');
        if (is_null($this->currentIntegrateModel) && $configIntegrateRpValue != 'none') {
            // FIXME: do something to get current integrate class
            $class = self::$LIST_RP_INTEGRATE[$configIntegrateRpValue][0]['class'];

            $this->currentIntegrateModel = $this->objectManager->create($class);
        }

        return $this->currentIntegrateModel;
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    public function getQuoteRPData()
    {
        return $this->getCurrentIntegrateModel()->getQuoteRPData()->getOutput();
    }

    /**
     * @param $data
     *
     * @return void
     */
    public function saveRPDataBeforeQuoteCollect($data)
    {
        $this->getCurrentIntegrateModel()->saveRPDataBeforeQuoteCollect($data);
    }


    /**
     * @param $id
     *
     * @return
     */
    public function getTransactionByOrder($id)
    {
        return $this->getCurrentIntegrateModel()->getTransactionByOrder($id);
    }
}
