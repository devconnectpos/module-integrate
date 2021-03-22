<?php

namespace SM\Integrate\Warehouse\Plugin\Collection;

class AbstractCollection
{
    /**
     * @param $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\DataObject $dataObject
     * @return $this|mixed
     */
    public function aroundAddItem(
        $subject,
        \Closure $proceed,
        \Magento\Framework\DataObject $dataObject
    ) {
        try {
            return $proceed($dataObject);
        } catch (\Exception $e) {
            return $this;
        }
    }
}
