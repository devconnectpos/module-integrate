<?php

namespace SM\Integrate\RewardPoint\Magento2EE;

if (class_exists("\Magento\Reward\Model\Reward")) {
	class Reward extends \Magento\Reward\Model\Reward
	{
		const REWARD_ACTION_REFUND_WITHOUT_RECEIPT = 99;
		
		protected function _construct()
		{
			parent::_construct();
			self::$_actionModelClasses = self::$_actionModelClasses + [
					self::REWARD_ACTION_REFUND_WITHOUT_RECEIPT => \SM\Integrate\RewardPoint\Magento2EE\Action\RefundWithoutReceipt::class,
				];
		}
		
		/**
		 * Return rate direction by action
		 *
		 * @return integer
		 */
		public function getRateDirectionByAction()
		{
			switch ($this->getAction()) {
				case self::REWARD_ACTION_ORDER_EXTRA:
				case self::REWARD_ACTION_REFUND_WITHOUT_RECEIPT:
					$direction = \Magento\Reward\Model\Reward\Rate::RATE_EXCHANGE_DIRECTION_TO_POINTS;
					break;
				default:
					$direction = \Magento\Reward\Model\Reward\Rate::RATE_EXCHANGE_DIRECTION_TO_CURRENCY;
					break;
			}
			return $direction;
		}
	}
} else {
	class Reward
	{
	
	}
}
