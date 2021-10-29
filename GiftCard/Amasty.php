<?php

namespace SM\Integrate\GiftCard;

use SM\Integrate\GiftCard\Contract\AbstractGCIntegrate;
use SM\Integrate\GiftCard\Contract\GCIntegrateInterface;

class Amasty extends AbstractGCIntegrate implements GCIntegrateInterface
{
    /**
     * @param $giftCardData
     *
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function saveGCDataBeforeQuoteCollect($giftCardData)
    {
        if ($this->getQuote()->getItems() === null) {
            $this->getQuote()->setItems([]);
        }

        foreach ($giftCardData as $data) {
            if (isset($data['is_delete']) && $data['is_delete'] === true) {
                $this->removeGiftCard($data);
                continue;
            }

            if (!isset($data['gift_code'])) {
                continue;
            }

            $data['gift_code'] = preg_replace('/\s+/', '', $data['gift_code']);
            $giftCardCode = $data['gift_code'];
            $this->getGiftCardAccountManagement()->applyGiftCardToCart($this->getQuote()->getId(), $giftCardCode);
        }

        $this->getQuote()->getShippingAddress()->setCollectShippingRates(true);
    }

    /**
     * @inheritDoc
     */
    public function getQuoteGCData()
    {
        // TODO: Implement getQuoteGCData() method.
    }

    public function removeGiftCard($giftData)
    {
        // TODO: Implement removeGiftCard() method.
    }

    public function getRefundToGCProductId()
    {
        // TODO: Implement getRefundToGCProductId() method.
    }

    /**
     * @return array
     */
    public function getGCCodePool()
    {
        $codePools = $this->getCodePoolRepository()->getList();
        $listCodePool = [];

        foreach ($codePools as $item) {
            $listCodePool[] = $item->getData();
        }

        return $listCodePool;
    }

    public function updateRefundToGCProduct($data)
    {
        // TODO: Implement updateRefundToGCProduct() method.
    }

    /**
     * @return \Amasty\GiftCard\Api\CodePoolRepositoryInterface|mixed
     */
    private function getCodePoolRepository()
    {
        return $this->objectManager->get('Amasty\GiftCard\Api\CodePoolRepositoryInterface');
    }

    private function getGiftCardAccountManagement()
    {
        return $this->objectManager->get('\Amasty\GiftCardAccount\Model\GiftCardAccount\GiftCardAccountManagement');
    }
}
