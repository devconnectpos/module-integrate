<?php

namespace SM\Integrate\GiftCard;

use Magento\Framework\Exception\CouldNotDeleteException;
use SM\Integrate\GiftCard\Contract\AbstractGCIntegrate;
use SM\Integrate\GiftCard\Contract\GCIntegrateInterface;

class Amasty extends AbstractGCIntegrate implements GCIntegrateInterface
{
    const GIFT_CARD_ID = 'id';
    const GIFT_CARD_CODE = 'code';
    const GIFT_CARD_AMOUNT = 'amount';
    const GIFT_CARD_BASE_AMOUNT = 'b_amount';

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

        if (!$this->getQuote()->getItemsCount()) {
            return;
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
        $quoteListGC = [];
        $quote = $this->getQuote();
        $quoteGiftCards = [];

        if ($extensionAttributes = $quote->getExtensionAttributes()) {
            $quoteGiftCards = $extensionAttributes->getAmGiftcardQuote() ? $extensionAttributes->getAmGiftcardQuote()->getGiftCards() : [];
        }

        if (is_array($quoteGiftCards) && count($quoteGiftCards) > 0) {
            foreach ($quoteGiftCards as $giftCard) {
                $gcCode = $giftCard[self::GIFT_CARD_CODE] ?? '';
                $gcBaseAmount = isset($giftCard[self::GIFT_CARD_BASE_AMOUNT]) ? -$giftCard[self::GIFT_CARD_BASE_AMOUNT] : 0;
                $gcAmount = isset($giftCard[self::GIFT_CARD_AMOUNT]) ? -$giftCard[self::GIFT_CARD_AMOUNT] : 0;
                $quoteGcData = [
                    'is_valid'             => true,
                    'gift_code'            => $gcCode,
                    'base_giftcard_amount' => $gcBaseAmount,
                    'giftcard_amount'      => $gcAmount,
                ];
                $quoteListGC[] = $quoteGcData;
            }
        }

        return $quoteListGC;
    }

    /**
     * @param $giftData
     *
     * @throws CouldNotDeleteException
     */
    public function removeGiftCard($giftData)
    {
        $quote = $this->getQuote();
        $quoteGiftCards = [];

        if (!$quote->getItemsCount()) {
            throw new CouldNotDeleteException(__('The "%1" Cart doesn\'t contain products.', $quote->getId()));
        }

        if ($extensionAttributes = $quote->getExtensionAttributes()) {
            $quoteGiftCards = $extensionAttributes->getAmGiftcardQuote() ? $extensionAttributes->getAmGiftcardQuote()->getGiftCards() : [];
        }

        if (is_array($quoteGiftCards) && count($quoteGiftCards) > 0) {
            foreach ($quoteGiftCards as $giftCard) {
                try {
                    $gc = $this->getAccountRepository()->getByCode($giftCard[self::GIFT_CARD_CODE] ?? '');
                    $this->getGiftCardCartProcessor()->removeFromCart($gc, $quote);
                } catch (\Exception $e) {
                    throw new CouldNotDeleteException(__("The gift card couldn't be deleted from the quote: " . $e->getMessage()));
                }
            }
        }

    }

    public function getRefundToGCProductId()
    {
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

    /**
     * @return \Amasty\GiftCardAccount\Model\GiftCardAccount\GiftCardAccountManagement|mixed
     */
    private function getGiftCardAccountManagement()
    {
        return $this->objectManager->get('Amasty\GiftCardAccount\Model\GiftCardAccount\GiftCardAccountManagement');
    }

    /**
     * @return \Amasty\GiftCardAccount\Model\GiftCardAccount\Repository|mixed
     */
    private function getAccountRepository()
    {
        return $this->objectManager->get('Amasty\GiftCardAccount\Model\GiftCardAccount\Repository');
    }

    /**
     * @return \Amasty\GiftCardAccount\Model\GiftCardAccount\GiftCardCartProcessor|mixed
     */
    private function getGiftCardCartProcessor()
    {
        return $this->objectManager->get('Amasty\GiftCardAccount\Model\GiftCardAccount\GiftCardCartProcessor');
    }
}
