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
	}
} else {
	class Reward
	{
	
	}
}
