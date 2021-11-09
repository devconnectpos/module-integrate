<?php

namespace SM\Integrate\GiftCard;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreRepository;
use SM\Integrate\GiftCard\Contract\AbstractGCIntegrate;
use SM\Integrate\GiftCard\Contract\GCIntegrateInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollection;
use Magento\Eav\Model\Entity\Type;

class Amasty extends AbstractGCIntegrate implements GCIntegrateInterface
{
    const GIFT_CARD_ID = 'id';
    const GIFT_CARD_CODE = 'code';
    const GIFT_CARD_AMOUNT = 'amount';
    const GIFT_CARD_BASE_AMOUNT = 'b_amount';
    const GIFT_CARD_REFUND_TO_GC_SKU = 'refund_to_gift_card_amasty';
    const TYPE_VIRTUAL = 1;
    const TYPE_PRINTED = 2;
    const TYPE_COMBINED = 3;

    /**
     * @var
     */
    protected $refundToGCProductId;

    /**
     * @var AttributeSetCollection
     */
    protected $attributeSetCollection;

    /**
     * @param StoreRepository $storeRepository
     */
    protected $storeRepository;

    public function __construct(
        ObjectManagerInterface $objectManager,
        AttributeSetCollection $attributeSetCollection,
        StoreRepository $storeRepository
    ) {
        $this->attributeSetCollection = $attributeSetCollection;
        $this->storeRepository = $storeRepository;
        parent::__construct($objectManager);
    }

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
            $quoteGiftCards = $extensionAttributes->getAmAppliedGiftCards() ?? [];
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
                    throw new CouldNotDeleteException(__("The gift card couldn't be deleted from the quote: ".$e->getMessage()));
                }
            }
        }
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function getRefundToGCProductId()
    {
        if (is_null($this->refundToGCProductId)) {
            /** @var \Magento\Catalog\Model\Product $productModel */
            $productModel = $this->objectManager->create('Magento\Catalog\Model\Product');
            $this->refundToGCProductId = $productModel->getResource()->getIdBySku(self::GIFT_CARD_REFUND_TO_GC_SKU);

            if (!$this->refundToGCProductId) {
                $this->refundToGCProductId = $this->createRefundToGCProduct()->getId();
            }
        }

        return $this->refundToGCProductId;
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
     * @return \Magento\Catalog\Model\Product
     * @throws \Exception
     */
    private function createRefundToGCProduct()
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $this->objectManager->create('Magento\Catalog\Model\Product');
        $product->setUrlKey(uniqid("amasty_refund_giftcard", false));
        $product->setName('Amasty Refund to Gift Card Product');
        $product->setTypeId('amgiftcard');
        $product->setStatus(2);
        $product->setAttributeSetId($this->getAttributeSetForRefundToGCProduct());
        $product->setSku(self::GIFT_CARD_REFUND_TO_GC_SKU);
        $product->setVisibility(4);
        $product->setPrice(0);
        $product->setWebsiteIds($this->toOptionArrayWebsite());
        $product->setStockData(
            [
                'use_config_manage_stock'          => 0, //'Use config settings' checkbox
                'manage_stock'                     => 0, //manage stock
                'min_sale_qty'                     => 1, //Minimum Qty Allowed in Shopping Cart
                'max_sale_qty'                     => 2, //Maximum Qty Allowed in Shopping Cart
                'is_in_stock'                      => 1, //Stock Availability
                'qty'                              => 999999, //qty,
                'original_inventory_qty'           => '999999',
                'use_config_min_qty'               => '0',
                'use_config_min_sale_qty'          => '0',
                'use_config_max_sale_qty'          => '0',
                'is_qty_decimal'                   => '1',
                'is_decimal_divided'               => '0',
                'use_config_backorders'            => '1',
                'use_config_notify_stock_qty'      => '0',
                'use_config_enable_qty_increments' => '0',
                'use_config_qty_increments'        => '0',
            ]
        );

        $product->setData('am_giftcard_type', self::TYPE_COMBINED);
        $product->setData('am_allow_open_amount', true);
        $product->setData('am_open_amount_min', 0.01);
        $product->setData('am_open_amount_max', 99999);

        return $product->save();
    }

    /**
     * PERFECT CODE
     *
     * @return int
     */
    private function getAttributeSetForRefundToGCProduct()
    {
        $productEntityTypeId = $this->objectManager->create(Type::class)
            ->loadByCode('catalog_product')
            ->getId();
        $eavAttributeSetCollection = $this->attributeSetCollection->create();

        $eavAttributeSetCollection->addFieldToFilter('attribute_set_name', 'Default')
            ->addFieldToFilter('entity_type_id', $productEntityTypeId);

        $id = $eavAttributeSetCollection->getFirstItem()->getId();

        if (is_null($id)) {
            $eavAttributeSetCollection = $this->attributeSetCollection->create();

            return $eavAttributeSetCollection->addFieldToFilter('entity_type_id', $productEntityTypeId)
                ->getFirstItem()
                ->getId();
        }

        return $id;
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

    /**
     * @return array
     */
    private function toOptionArrayWebsite()
    {
        $stores = $this->storeRepository->getList();
        $websiteIds = [];
        foreach ($stores as $store) {
            if ($store->getWebsiteId() != 0) {
                $websiteIds[] = $store["website_id"];
            }
        }
        $websiteIds = array_unique($websiteIds);

        return $websiteIds;
    }
}
