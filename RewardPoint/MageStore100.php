<?php
/**
 * Created by IntelliJ IDEA.
 * User: vjcspy
 * Date: 20/03/2017
 * Time: 18:00
 */

namespace SM\Integrate\RewardPoint;

use SM\Integrate\RewardPoint\Contract\RPIntegrateInterface;

/**
 * Class MageStore100
 *
 * @package SM\Integrate\RewardPoint
 */
class MageStore100 implements RPIntegrateInterface
{

    /**
     * @inheritdoc
     */
    public function saveRPDataBeforeQuoteCollect($data)
    {
        // TODO: Implement saveRPDataBeforeQuoteCollect() method.
    }

    /**
     * @inheritdoc
     */
    public function getQuoteRPData()
    {
        // TODO: Implement getRPDataAfterQuoteCollect() method.
    }

    /**
     * @inheritdoc
     */
    public function getCurrentPointBalance($customerId, $scope = null)
    {
        // TODO: Implement getCurrentPointBalance() method.
    }

    public function getTransactionByOrder()
    {
        // TODO: Implement getCurrentPointBalance() method.
    }
}
