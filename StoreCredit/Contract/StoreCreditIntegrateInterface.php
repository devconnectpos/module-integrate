<?php
/**
 * Created by Hung Nguyen - hungnh@smartosc.com
 * Date: 03/12/2018
 * Time: 10:17
 */

namespace SM\Integrate\StoreCredit\Contract;

use SM\Integrate\Data\StoreCreditQuoteData;

interface StoreCreditIntegrateInterface
{
    /**
     * @param $customer
     * @param $scope
     * @return mixed
     */
    public function getStoreCreditCollection($customer, $scope = null);

    /**
     * @param $data
     *
     * @return void
     */
    public function saveStoreCreditDataBeforeQuoteCollect($data);

    /**
     * @return StoreCreditQuoteData
     */
    public function getQuoteStoreCreditData();

    /**
     *
     * @param      $customerId
     * @param null $scope
     *
     * @return float
     */
    public function getCurrentStoreCreditBalance($customerId, $scope = null);

    /**
     * @param $customer
     * @param $websiteId
     * @param $storeId
     * @param $amountDelta
     * @return mixed
     */
    public function updateCustomerStoreCreditBalance($customer, $websiteId, $storeId, $amountDelta);
}
