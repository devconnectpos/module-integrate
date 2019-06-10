<?php

namespace SM\Integrate\Warehouse\Plugin;

use Magento\InventoryCatalog\Model\BulkSourceUnassign;
use SM\Integrate\Warehouse\Plugin\Contract\SourceAbstract;

class SyncProductUnassignSource extends SourceAbstract
{
    /**
     * @param BulkSourceUnassign $subject
     * @param $result
     * @param $skus
     * @return mixed|void
     * @throws \Exception
     */
    public function afterExecute(
        BulkSourceUnassign $subject,
        $result,
        $skus
    )
    {
        return $this->syncSource($result, $skus);
    }
}
