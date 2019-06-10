<?php

namespace SM\Integrate\Warehouse\Plugin;

use Magento\InventoryCatalog\Model\BulkSourceAssign;
use SM\Integrate\Warehouse\Plugin\Contract\SourceAbstract;

class SyncProductAssignSource extends SourceAbstract
{
    /**
     * @param \Magento\InventoryCatalog\Model\BulkSourceAssign $subject
     * @param $result
     * @param $skus
     * @return mixed
     * @throws \Exception
     */
    public function afterExecute(
        BulkSourceAssign $subject,
        $result,
        $skus
    ) {
        return $this->syncSource($result, $skus);
    }
}
