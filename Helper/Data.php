<?php
/**
 * Created by IntelliJ IDEA.
 * User: vjcspy
 * Date: 20/03/2017
 * Time: 18:51
 */

namespace SM\Integrate\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\ObjectManagerInterface;

/**
 * Class Data
 *
 * @package SM\Integrate\Helper
 */
class Data
{

    private $isIntegrateRp;
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;
    private $isIntegrateGc;
    protected $isIntegrateWh;
    protected $isIntegrateStoreCredit;
    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    private $moduleList;
    private $isIntegrateMultipleWareHouse;

    private $isIntegrateGcPWA;
    private $isIntegrateRpPWA;

    /**
     * Data constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface          $objectManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Module\ModuleListInterface      $moduleList
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        ScopeConfigInterface $scopeConfig,
        ModuleListInterface $moduleList
    )
    {
        $this->objectManager = $objectManager;
        $this->scopeConfig   = $scopeConfig;
        $this->moduleList    = $moduleList;
    }

    /**
     * @return bool
     */
    public function isIntegrateRP()
    {
        if (is_null($this->isIntegrateRp)) {
            $configValue = $this->scopeConfig->getValue('xretail/pos/integrate_rp');
            if (!!$configValue && $configValue !== 'none') {
                if ($configValue === 'aheadWorks' && $this->isAHWRewardPoints()) {
                    $this->isIntegrateRp = true;
                } elseif ($configValue === 'mage2_ee' && $this->isRewardPointMagento2EE()) {
                    $this->isIntegrateRp = true;
                } else {
                    $this->isIntegrateRp = false;
                }
            }
        }

        return $this->isIntegrateRp;
    }

    /**
     * @return bool
     */
    public function isIntegrateRPInPWA()
    {
        if (is_null($this->isIntegrateRpPWA)) {
            $configValue = $this->scopeConfig->getValue('pwa/integrate/pwa_integrate_reward_points');
            if (!!$configValue && $configValue === 'true' && $this->isAHWRewardPointsExist()) {
                $this->isIntegrateRpPWA = true;
            } else {
                $this->isIntegrateRpPWA = false;
            }
        }

        return $this->isIntegrateRpPWA;
    }

    /**
     * @return bool
     */
    public function isIntegrateGCInPWA()
    {
        if (is_null($this->isIntegrateGcPWA)) {
            $configValue          = $this->scopeConfig->getValue('pwa/integrate/pwa_integrate_gift_card');
            if (!!$configValue && $configValue === 'true' && $this->isAHWGiftCardxist()) {
                $this->isIntegrateGcPWA = true;
            } else {
                $this->isIntegrateGcPWA = false;
            }
        }

        return $this->isIntegrateGcPWA;
    }

    /**
     * @return bool
     */
    public function isIntegrateStoreCredit()
    {
        if (is_null($this->isIntegrateStoreCredit)) {
            $configValue = $this->scopeConfig->getValue('xretail/pos/integrate_store_credit');
            $this->isIntegrateStoreCredit = !!$configValue && $configValue !== 'none';
        }

        return $this->isIntegrateStoreCredit;
    }

    /**
     * @return bool
     */
    public function isIntegrateGC()
    {
        if (is_null($this->isIntegrateGc)) {
            $configValue          = $this->scopeConfig->getValue('xretail/pos/integrate_gc');
            if (!!$configValue && $configValue !== 'none') {
                if ($configValue === 'aheadWorks' && $this->isAHWGiftCardxist()) {
                    $this->isIntegrateGc = true;
                } elseif ($configValue === 'mage2_ee' && $this->isGiftCardMagento2EE()) {
                    $this->isIntegrateGc = true;
                } else {
                    $this->isIntegrateGc = false;
                }
            }
        }

        return $this->isIntegrateGc;
    }

    /**
     * @return bool
     */
    public function isIntegrateMultipleWareHouse()
    {
        if (is_null($this->isIntegrateMultipleWareHouse)) {
            $configValue          = $this->scopeConfig->getValue('xretail/pos/integrate_wh');
            if (!!$configValue && $configValue !== 'none') {
                if ($configValue === 'bms' && $this->isIntegrateWH()) {
                    $this->isIntegrateMultipleWareHouse = true;
                } elseif ($configValue === 'magento_inventory' && $this->isMagentoInventory()) {
                    $this->isIntegrateMultipleWareHouse = true;
                } else {
                    $this->isIntegrateMultipleWareHouse = false;
                }
            } else {
                $this->isIntegrateMultipleWareHouse = false;
            }
        }

        return $this->isIntegrateMultipleWareHouse;
    }

    /**
     * @return bool
     */
    public function isIntegrateWH()
    {
        return !!$this->moduleList->getOne("BoostMyShop_AdvancedStock");
    }

    public function isMagentoInventory()
    {
        return !!$this->moduleList->getOne("Magento_Inventory");
    }

    public function isAHWGiftCardxist()
    {
        return !!$this->moduleList->getOne("Aheadworks_Giftcard");
    }

    public function isAHWRewardPoints()
    {
        $configValue = $this->scopeConfig->getValue('xretail/pos/integrate_rp');
        return !!$this->moduleList->getOne("Aheadworks_RewardPoints") && $configValue === 'aheadWorks';
    }

    public function isAHWRewardPointsExist()
    {
        return !!$this->moduleList->getOne("Aheadworks_RewardPoints");
    }

    public function isGiftCardMagento2EE()
    {
        return !!$this->moduleList->getOne("Magento_GiftCardAccount");
    }

    public function isRewardPointMagento2EE()
    {
        $configValue = $this->scopeConfig->getValue('xretail/pos/integrate_rp');
        return !!$this->moduleList->getOne("Magento_Reward") && $configValue === 'mage2_ee';
    }

    public function isRewardPointMagento2EEExist()
    {
        return !!$this->moduleList->getOne("Magento_Reward");
    }


    public function isExistStoreCreditMagento2EE()
    {
        return !!$this->moduleList->getOne("Magento_CustomerBalance");
    }

    /**
     * @return \SM\Integrate\Model\RPIntegrateManagement
     */
    public function getRpIntegrateManagement()
    {
        return $this->objectManager->get('SM\Integrate\Model\RPIntegrateManagement');
    }

    public function getGcIntegrateManagement()
    {
        return $this->objectManager->get('SM\Integrate\Model\GCIntegrateManagement');
    }

    /**
     * @return \SM\Integrate\Model\WarehouseIntegrateManagement
     */
    public function getWarehouseIntegrateManagement()
    {
        return $this->objectManager->get('SM\Integrate\Model\WarehouseIntegrateManagement');
    }

    /**
     * @return \SM\Integrate\Model\StoreCreditIntegrateManagement
     */
    public function getStoreCreditIntegrateManagement()
    {
        return $this->objectManager->get('SM\Integrate\Model\StoreCreditIntegrateManagement');
    }
}
