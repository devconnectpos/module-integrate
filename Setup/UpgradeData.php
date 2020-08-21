<?php
declare(strict_types=1);

namespace SM\Integrate\Setup;

use Magento\Framework\DB\Ddl\Table;

class UpgradeData implements \Magento\Framework\Setup\UpgradeDataInterface
{
    /**
     * @var \Magento\Sales\Setup\SalesSetupFactory
     */
    private $salesSetupFactory;

    public function __construct(\Magento\Sales\Setup\SalesSetupFactory $salesSetupFactory)
    {
        $this->salesSetupFactory = $salesSetupFactory;
    }

    public function upgrade(\Magento\Framework\Setup\ModuleDataSetupInterface $setup, \Magento\Framework\Setup\ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        if (version_compare($context->getVersion(), '0.0.2', '<=')) {
            $this->addAwRewardPointsAttributes($installer);
        }

        $installer->endSetup();
    }

    protected function addAwRewardPointsAttributes(\Magento\Framework\Setup\ModuleDataSetupInterface $installer)
    {
        $salesSetup = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $installer]);

        $attributes = [
            'base_aw_reward_points_cancelled' => Table::TYPE_DECIMAL,
            'aw_reward_points_cancelled' => Table::TYPE_DECIMAL,
            'aw_reward_points_blnce_cancelled' => Table::TYPE_INTEGER,
            'base_aw_reward_points_residual' => Table::TYPE_DECIMAL,
            'aw_reward_points_residual' => Table::TYPE_DECIMAL,
            'aw_reward_points_blnce_residual' => Table::TYPE_INTEGER,
        ];

        foreach ($attributes as $code => $type) {
            $salesSetup->addAttribute('creditmemo', $code, ['type' => $type, 'visible' => false]);
            $salesSetup->addAttribute('creditmemo_item', $code, ['type' => $type, 'visible' => false]);
        }
    }
}
