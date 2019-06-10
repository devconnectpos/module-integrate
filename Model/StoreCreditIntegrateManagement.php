<?php
/**
 * Created by Hung Nguyen - hungnh@smartosc.com
 * Date: 03/12/2018
 * Time: 10:13
 */

namespace SM\Integrate\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\Integrate\StoreCredit\Contract\StoreCreditIntegrateInterface;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;

/**
 * Class StoreCreditIntegrateManagement
 *
 * @package SM\Integrate\Model
 */
class StoreCreditIntegrateManagement extends ServiceAbstract
{

    /**
     * @var StoreCreditIntegrateInterface
     */
    protected $currentIntegrateModel;

    /**
     * @var array
     */
    public static $LIST_STORE_CREDIT_INTEGRATE
        = [
            'mage2_ee' => [
                [
                    "version" => "~1.0.0",
                    "class"   => "SM\\Integrate\\StoreCredit\\Magento2EE"
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
     * StoreCreditIntegrateManagement constructor.
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
     * @return \SM\Integrate\StoreCredit\Contract\StoreCreditIntegrateInterface
     */
    public function getCurrentIntegrateModel()
    {
        $configIntegrateStoreCreditValue = $this->scopeConfig->getValue('xretail/pos/integrate_store_credit');
        if (is_null($this->currentIntegrateModel)) {
            // FIXME: do something to get current integrate class
            $class = self::$LIST_STORE_CREDIT_INTEGRATE[$configIntegrateStoreCreditValue][0]['class'];

            $this->currentIntegrateModel = $this->objectManager->create($class);
        }

        return $this->currentIntegrateModel;
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    public function getQuoteStoreCreditData()
    {
        return $this->getCurrentIntegrateModel()->getQuoteStoreCreditData()->getOutput();
    }

    /**
     * @param $data
     *
     * @return void
     */
    public function saveStoreCreditDataBeforeQuoteCollect($data)
    {
        $this->getCurrentIntegrateModel()->saveStoreCreditDataBeforeQuoteCollect($data);
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
