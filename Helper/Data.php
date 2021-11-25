<?php
/**
 * Created by IntelliJ IDEA.
 * User: vjcspy
 * Date: 20/03/2017
 * Time: 18:51
 */

namespace SM\Integrate\Helper;

use Magento\Config\Model\Config\Loader;
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
     * @var \Magento\Config\Model\Config\Loader
     */
    protected $configLoader;
    protected $configData;

    /**
     * Data constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface          $objectManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Module\ModuleListInterface      $moduleList
     * @param \Magento\Config\Model\Config\Loader                $loader
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        ScopeConfigInterface $scopeConfig,
        ModuleListInterface $moduleList,
        Loader $loader
    ) {
        $this->objectManager = $objectManager;
        $this->scopeConfig   = $scopeConfig;
        $this->moduleList    = $moduleList;
        $this->configLoader  = $loader;
    }

    /**
     * @return mixed
     */
    public function getConfigLoaderData()
    {
        if ($this->configData === null) {
            $this->configData = $this->configLoader->getConfigByPath('xretail/pos', 'default', 0);
        }
        return $this->configData;
    }

    /**
     * @return bool
     */
    public function isIntegrateRP()
    {
        if (is_null($this->isIntegrateRp)) {
            $config = $this->getConfigLoaderData();
            $configValue          = isset($config['xretail/pos/integrate_rp']) ? $config['xretail/pos/integrate_rp']['value'] : 'none';
            if (!!$configValue && $configValue !== 'none') {
                if ($configValue === 'aheadWorks' && $this->isAHWRewardPoints()) {
                    $this->isIntegrateRp = true;
                } elseif ($configValue === 'amasty' && $this->isAmastyRewardPoints()) {
                    $this->isIntegrateRp = true;
                } elseif ($configValue === 'mirasvit' && $this->isMirasvitRewardPoints()) {
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
            if (!!$configValue && $configValue === 'true' && $this->isAHWGiftCardExist()) {
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
            $config      = $this->getConfigLoaderData();
            $configValue = isset($config['xretail/pos/integrate_store_credit']) ? $config['xretail/pos/integrate_store_credit']['value'] : 'none';

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
            $config      = $this->getConfigLoaderData();
            $configValue = isset($config['xretail/pos/integrate_gc']) ? $config['xretail/pos/integrate_gc']['value'] : 'none';
            if (!!$configValue && $configValue !== 'none') {
                if ($configValue === 'aheadWorks' && $this->isAHWGiftCardExist()) {
                    $this->isIntegrateGc = true;
                } elseif ($configValue === 'mage2_ee' && $this->isGiftCardMagento2EE()) {
                    $this->isIntegrateGc = true;
                } elseif ($configValue === 'amasty' && $this->isGiftCardAmasty()) {
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
    public function isIntegrateAcumaticaCloudERP()
    {
        $config      = $this->getConfigLoaderData();
        $configValue = isset($config['xretail/pos/integrate_cloud_erp']) ? $config['xretail/pos/integrate_cloud_erp']['value'] : 'none';
        return $configValue === 'acumatica';
    }

    /**
     * @return bool
     */
    public function isIntegrateStorePickUpExtension()
    {
        $config      = $this->getConfigLoaderData();
        $configValue = isset($config['xretail/pos/integrate_store_pick_up_extension']) ? $config['xretail/pos/integrate_store_pick_up_extension']['value'] : 'none';
        return $configValue === 'mageworx';
    }

    /**
     * @return bool
     */
    public function isIntegrateRMAExtension()
    {
        $config      = $this->getConfigLoaderData();
        $configValue = isset($config['xretail/pos/integrate_rma_extension']) ? $config['xretail/pos/integrate_rma_extension']['value'] : 'none';
        return $configValue === 'aheadWorks';
    }

    /**
     * @return bool
     */
    public function isIntegrateMultipleWareHouse()
    {
        if (is_null($this->isIntegrateMultipleWareHouse)) {
            $config      = $this->getConfigLoaderData();
            $configValue = isset($config['xretail/pos/integrate_wh']) ? $config['xretail/pos/integrate_wh']['value'] : 'none';
            if (!!$configValue && $configValue !== 'none') {
                if ($configValue === 'bms' && $this->isIntegrateWH()) {
                    $this->isIntegrateMultipleWareHouse = true;
                } elseif ($configValue === 'magento_inventory' && $this->isMagentoInventory()) {
                    $this->isIntegrateMultipleWareHouse = true;
                } elseif ($configValue === 'mage_store' && $this->isMagestoreInventory()) {
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

    /**
     * @return bool
     */
    public function isMagentoInventory()
    {
        return !!$this->moduleList->getOne("Magento_Inventory");
    }

    /**
     * @return bool
     */
    public function isMagestoreInventory()
    {
        return !!$this->moduleList->getOne("Magestore_InventorySuccess");
    }

    /**
     * @return bool
     */
    public function isExistPektsekyeOptionBundle()
    {
        return !!$this->moduleList->getOne("Pektsekye_OptionBundle");
    }

    /**
     * @return bool
     */
    public function isExistMageWorx()
    {
        return !!$this->moduleList->getOne("MageWorx_Locations");
    }

    /**
     * @return bool
     */
    public function isExistAheadWorksRMA()
    {
        return !!$this->moduleList->getOne("Aheadworks_Rma");
    }

    /**
     * @return bool
     */
    public function isAHWRewardPoints()
    {
        $config      = $this->getConfigLoaderData();
        $configValue = isset($config['xretail/pos/integrate_rp']) ? $config['xretail/pos/integrate_rp']['value'] : 'none';
        return !!$this->moduleList->getOne("Aheadworks_RewardPoints") && $configValue === 'aheadWorks';
    }

    /**
     * @return bool
     */
    public function isAHWRewardPointsExist()
    {
        return !!$this->moduleList->getOne("Aheadworks_RewardPoints");
    }

    /**
     * @return bool
     */
    public function isAmastyRewardPoints()
    {
        $config      = $this->getConfigLoaderData();
        $configValue = isset($config['xretail/pos/integrate_rp']) ? $config['xretail/pos/integrate_rp']['value'] : 'none';
        return !!$this->moduleList->getOne("Amasty_Rewards") && $configValue === 'amasty';
    }

    /**
     * @return bool
     */
    public function isAmastyRewardPointsExist()
    {
        return !!$this->moduleList->getOne("Amasty_Rewards");
    }

    /**
     * @return bool
     */
    public function isMirasvitRewardPoints()
    {
        $config      = $this->getConfigLoaderData();
        $configValue = isset($config['xretail/pos/integrate_rp']) ? $config['xretail/pos/integrate_rp']['value'] : 'none';
        return !!$this->moduleList->getOne("Mirasvit_Rewards") && $configValue === 'mirasvit';
    }

    /**
     * @return bool
     */
    public function isAHWGiftCardExist()
    {
        return !!$this->moduleList->getOne("Aheadworks_Giftcard");
    }

    /**
     * @return bool
     */
    public function isGiftCardMagento2EE()
    {
        return !!$this->moduleList->getOne("Magento_GiftCardAccount");
    }

    /**
     * @return bool
     */
    public function isGiftCardAmasty()
    {
        return !!$this->moduleList->getOne("Amasty_GiftCard");
    }

    /**
     * @return bool
     */
    public function isUsingAHWGiftCard() {
        $config      = $this->getConfigLoaderData();
        $configValue = isset($config['xretail/pos/integrate_rp']) ? $config['xretail/pos/integrate_rp']['value'] : 'none';

        return $this->isAHWGiftCardExist() && $configValue === 'aheadWorks';
    }

    /**
     * @return bool
     */
    public function isUsingMagentoGiftCard() {
        $config      = $this->getConfigLoaderData();
        $configValue = isset($config['xretail/pos/integrate_rp']) ? $config['xretail/pos/integrate_rp']['value'] : 'none';

        return $this->isGiftCardMagento2EE() && $configValue === 'mage2_ee';
    }

    /**
     * @return bool
     */
    public function isUsingAmastyGiftCard() {
        $config      = $this->getConfigLoaderData();
        $configValue = isset($config['xretail/pos/integrate_gc']) ? $config['xretail/pos/integrate_gc']['value'] : 'none';

        return $this->isGiftCardAmasty() && $configValue === 'amasty';
    }

    /**
     * @return bool
     */
    public function isRewardPointMagento2EE()
    {
        $config      = $this->getConfigLoaderData();
        $configValue = isset($config['xretail/pos/integrate_rp']) ? $config['xretail/pos/integrate_rp']['value'] : 'none';
        return !!$this->moduleList->getOne("Magento_Reward") && $configValue === 'mage2_ee';
    }

    /**
     * @return bool
     */
    public function isRewardPointMagento2EEExist()
    {
        return !!$this->moduleList->getOne("Magento_Reward");
    }

    /**
     * @return bool
     */
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

    /**
     * @return mixed
     */
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

    /**
     * @return bool
     */
    public function isExistKensiumCart()
    {
        return !!$this->moduleList->getOne("Kensium_Cart");
    }

    /**
     * @return bool
     */
    public function isExistSnmportalPdfprint()
    {
        return !!$this->moduleList->getOne("Snmportal_Pdfprint");
    }

    public function isIntegrateMageShip()
    {
        return !!$this->moduleList->getOne("Maurisource_MageShip");
    }

    public function isIntegrateShipperHQ()
    {
        return !!$this->moduleList->getOne("ShipperHQ_Shipper");
    }

    public function isIntegrateMatrixRate()
    {
        return !!$this->moduleList->getOne("WebShopApps_MatrixRate");
    }

    public function isExistBoldOrderComment()
    {
        return !!$this->moduleList->getOne("Bold_OrderComment");
    }

    public function isIntegrateBoldOrderComment()
    {
        $config      = $this->getConfigLoaderData();
        $configValue = isset($config['xretail/pos/integrate_order_comment_extensions']) ? $config['xretail/pos/integrate_order_comment_extensions']['value'] : 'none';
        return $this->isExistBoldOrderComment() && $configValue === 'boldCommerce';
    }

    public function isDeductRewardPointsWhenRefundWithoutReceipt()
    {
        $config      = $this->getConfigLoaderData();
        return $this->isRewardPointMagento2EE() && $config['xretail/pos/deduct_rp_when_refund_without_receipt']['value'] == 1;
    }

    public function isExistCustomShipping()
    {
        return !!$this->moduleList->getOne("Magecomp_Customshipping");
    }
}
