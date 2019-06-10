<?php
/**
 * Created by Hung Nguyen - hungnh@smartosc.com
 * Date: 03/12/2018
 * Time: 10:14
 */

namespace SM\Integrate\Data;

use SM\Core\Api\Data\Contract\ApiDataAbstract;

class StoreCreditQuoteData extends ApiDataAbstract
{

    public function getUseStoreCredit()
    {
        return $this->getData('use_store_credit');
    }

    public function getStoreCreditDiscountAmount()
    {
        return $this->getData('store_credit_discount_amount');
    }

    public function getBaseStoreCreditDiscountAmount()
    {
        return $this->getData('base_store_credit_discount_amount');
    }

    public function getCustomerBalanceCurrency()
    {
        return $this->getData('customer_balance_currency');
    }

    public function getCustomerBalanceBaseCurrency()
    {
        return $this->getData('customer_balance_base_currency');
    }
}
