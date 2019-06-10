<?php

namespace SM\Integrate\Warehouse\Plugin;

use Magento\InventoryCatalog\Model\BulkInventoryTransfer;
use SM\Integrate\Warehouse\Plugin\Contract\SourceAbstract;

class SyncProductTransferSource extends SourceAbstract
{

    /**
     * @param BulkInventoryTransfer $subject
     * @param $result
     * @param $skus
     * @return mixed|void
     * @throws \Exception
     */
    public function afterExecute(
        BulkInventoryTransfer $subject,
        $result,
        $skus
    )
    {
        return $this->syncSource($result, $skus);
    }
}
