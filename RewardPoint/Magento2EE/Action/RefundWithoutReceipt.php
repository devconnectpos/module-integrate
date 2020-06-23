<?php

namespace SM\Integrate\RewardPoint\Magento2EE\Action;
if (class_exists("\Magento\Reward\Model\Action\AbstractAction")) {
	class RefundWithoutReceipt extends \Magento\Reward\Model\Action\AbstractAction
	{
		/**
		 * Return action message for history log
		 *
		 * @param   array $args Additional history data
		 * @return \Magento\Framework\Phrase
		 */
		public function getHistoryMessage($args = [])
		{
			$transactionId = isset($args['transaction_id']) ? $args['transaction_id'] : '';
			return __('Refund Without Receipt ID #%1', $transactionId);
		}
		
		/**
		 * Setter for $_entity and add some extra data to history
		 *
		 * @param \Magento\Framework\DataObject $entity
		 * @return $this
		 * @codeCoverageIgnore
		 */
		public function setEntity($entity)
		{
			parent::setEntity($entity);
			$this->getHistory()->addAdditionalData(['transaction_id' => $this->getEntity()->getId()]);
			return $this;
		}
	}
} else {
	class RefundWithoutReceipt
	{
	
	}
}
