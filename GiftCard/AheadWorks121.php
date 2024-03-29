<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 10/13/17
 * Time: 3:00 PM
 */

namespace SM\Integrate\GiftCard;

use Aheadworks\Giftcard\Api\Data\ProductAttributeInterface;
use Aheadworks\Giftcard\Model\Source\Entity\Attribute\GiftcardType;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use SM\Integrate\GiftCard\Contract\AbstractGCIntegrate;
use SM\Integrate\GiftCard\Contract\GCIntegrateInterface;

class AheadWorks121 extends AbstractGCIntegrate implements GCIntegrateInterface
{

    protected $gcRepository;
    private $gcValidator;
    private $gcQuoteCollectionFactory;
    private $gcQuoteFactory;
    private $cartExtensionFactory;

    const AHW_REFUND_TO_GC_SKU = 'refund_to_ahw_gift_card';

    /**
     * @var
     */
    protected $refundToGCProductId;

    /**
     * @param $giftcardDatas
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function saveGCDataBeforeQuoteCollect($giftcardDatas)
    {
        if ($this->getQuote()->getItems() === null) {
            $this->getQuote()->setItems([]);
        }
        foreach ($giftcardDatas as $giftData) {
            if (isset($giftData['is_delete']) && $giftData['is_delete'] === true) {
                $this->removeGiftCard($giftData);
                continue;
            }

            if (!isset($giftData['gift_code'])) {
                continue;
            }

            $giftData['gift_code'] = preg_replace('/\s+/', '', $giftData['gift_code']);

            try {
                $giftcardCode = $giftData['gift_code'];
                $giftcard = $this->getGiftCardRepository()
                    ->getByCode($giftcardCode, $this->getQuote()->getStore()->getWebsiteId());
            } catch (NoSuchEntityException $e) {
                $mess = 'The specified Gift Card code :'.$giftData['gift_code'].' is not valid';
                throw new NoSuchEntityException(__($mess));
            }

            if (!$this->getGiftCardValidator()->isValid($giftcard)) {
                $messages = $this->getGiftCardValidator()->getMessages();
                throw new LocalizedException($messages[0]);
            }

            $giftcardQuoteItems = $this->getGiftCardQuoteCollectionFactory()
                ->create()
                ->addFieldToFilter('quote_id', $this->getQuote()->getId())
                ->addFieldToFilter('giftcard_id', $giftcard->getId())
                ->load()
                ->getItems();

            if ($giftcardQuoteItems) {
                throw new LocalizedException(__('The specified Gift Card code already in the quote'));
            }

            if ($this->getQuote()->getExtensionAttributes()
                && $this->getQuote()->getExtensionAttributes()->getAwGiftcardCodes()
            ) {
                $giftcards = $this->getQuote()
                    ->getExtensionAttributes()
                    ->getAwGiftcardCodes();
                /** @var GiftcardQuoteInterface $giftcard */
                foreach ($giftcards as $giftcardData) {
                    if ($giftcardData->getGiftcardCode() == $giftcard->getCode()) {
                        throw new LocalizedException(__('The specified Gift Card code already in the quote'));
                    }
                }
            }
            // for exchange the refund order using giftcard
            $maxValueGCAmountCanUse = 0;
            if (!!isset($giftData['max_gc_amount'])) {
                $maxValueGCAmountCanUse = $giftData['max_gc_amount'];
            }
            $this->addGiftcardToQuote($giftcard, $this->getQuote(), $maxValueGCAmountCanUse);
        }
        $this->getQuote()->getShippingAddress()->setCollectShippingRates(true);
    }

    /**
     * @param                            $giftcard
     * @param \Magento\Quote\Model\Quote $quote
     *
     * @return $this
     */
    protected function addGiftcardToQuote($giftcard, \Magento\Quote\Model\Quote $quote, $maxValueGCAmountCanUse)
    {
        $extensionAttributes = $quote->getExtensionAttributes()
            ? $quote->getExtensionAttributes()
            : $this->getCartExtensionFactory()->create();

        /** @var GiftcardQuoteInterface $giftcardQuoteObject */
        $giftcardQuoteObject = $this->getGiftCardQuoteFactory()->create();
        if ($maxValueGCAmountCanUse > 0 && $maxValueGCAmountCanUse < $giftcard->getBalance()) {
            $giftcardQuoteObject
                ->setGiftcardId($giftcard->getId())
                ->setGiftcardCode($giftcard->getCode())
                ->setGiftcardBalance($maxValueGCAmountCanUse)
                ->setQuoteId($quote->getId())
                ->setBaseGiftcardAmount($maxValueGCAmountCanUse)
                ->setMaxValueGCAmountCanUse($maxValueGCAmountCanUse);
        } else {
            $giftcardQuoteObject
                ->setGiftcardId($giftcard->getId())
                ->setGiftcardCode($giftcard->getCode())
                ->setGiftcardBalance($giftcard->getBalance())
                ->setQuoteId($quote->getId())
                ->setBaseGiftcardAmount($giftcard->getBalance())
                ->setMaxValueGCAmountCanUse($maxValueGCAmountCanUse);
        }
        $giftcards = [$giftcardQuoteObject];
        if ($extensionAttributes->getAwGiftcardCodes()) {
            $giftcards = array_merge($giftcards, $extensionAttributes->getAwGiftcardCodes());
        }
        $giftcards = $this->sortGiftcards($giftcards);
        $extensionAttributes->setAwGiftcardCodes($giftcards);

        $quote->setExtensionAttributes($extensionAttributes);

        return $this;
    }

    /**
     * @return \Magento\Quote\Api\Data\CartExtensionFactory
     */
    protected function getCartExtensionFactory()
    {
        if (is_null($this->cartExtensionFactory)) {
            $this->cartExtensionFactory = $this->objectManager->create('Magento\Quote\Api\Data\CartExtensionFactory');
        }

        return $this->cartExtensionFactory;
    }

    /**
     * @return \Aheadworks\Giftcard\Api\GiftcardRepositoryInterface
     */
    protected function getGiftCardRepository()
    {
        if (is_null($this->gcRepository)) {
            $this->gcRepository = $this->objectManager->create('Aheadworks\Giftcard\Api\GiftcardRepositoryInterface');
        }

        return $this->gcRepository;
    }

    /**
     * @return \Aheadworks\Giftcard\Model\Giftcard\Validator
     */
    protected function getGiftCardValidator()
    {
        if (is_null($this->gcValidator)) {
            $this->gcValidator = $this->objectManager->create('Aheadworks\Giftcard\Model\Giftcard\Validator');
        }

        return $this->gcValidator;
    }

    /**
     * @return \Aheadworks\Giftcard\Model\ResourceModel\Giftcard\Quote\CollectionFactory
     */
    protected function getGiftCardQuoteCollectionFactory()
    {
        if (is_null($this->gcQuoteCollectionFactory)) {
            $this->gcQuoteCollectionFactory = $this->objectManager->create(
                'Aheadworks\Giftcard\Model\ResourceModel\Giftcard\Quote\CollectionFactory'
            );
        }

        return $this->gcQuoteCollectionFactory;
    }

    /**
     * @return \Aheadworks\Giftcard\Api\Data\Giftcard\QuoteInterfaceFactory
     */
    protected function getGiftCardQuoteFactory()
    {
        if (is_null($this->gcQuoteFactory)) {
            $this->gcQuoteFactory = $this->objectManager->create(
                'Aheadworks\Giftcard\Api\Data\Giftcard\QuoteInterfaceFactory'
            );
        }

        return $this->gcQuoteFactory;
    }

    /**
     * @return array
     */
    public function getQuoteGCData()
    {
        $quoteListGC = [];
        $quote = $this->getQuote();
        $quoteGiftCards = [];
        if ($quote->getExtensionAttributes()) {
            $quoteGiftCards = $quote->getExtensionAttributes()->getAwGiftcardCodes();
        }
        if (is_array($quoteGiftCards) && count($quoteGiftCards) > 0) {
            foreach ($quoteGiftCards as $giftCard) {
                $quoteGcData = [
                    'is_valid'             => true,
                    'gift_code'            => $giftCard->getData('giftcard_code'),
                    'base_giftcard_amount' => -$giftCard->getData('base_giftcard_amount'),
                    'giftcard_amount'      => -$giftCard->getData('giftcard_amount'),
                    'max_gc_amount'        => $giftCard->getMaxValueGCAmountCanUse(),
                ];
                $quoteListGC[] = $quoteGcData;
            }
        }

        return $quoteListGC;
    }

    /**
     * Sort Gift Card codes by asc
     *
     * @param GiftcardQuoteInterface[] $giftcards
     *
     * @return GiftcardQuoteInterface[]
     */
    private function sortGiftcards($giftcards)
    {
        usort(
            $giftcards,
            function ($a, $b) {
                if ($a->getGiftcardBalance() == $b->getGiftcardBalance()) {
                    return 0;
                }

                return $a->getGiftcardBalance() > $b->getGiftcardBalance() ? 1 : -1;
            }
        );

        return $giftcards;
    }

    public function removeGiftCard($giftData)
    {
        $quote = $this->getQuote();
        if ($quote->getExtensionAttributes() && $quoteGiftcards = $quote->getExtensionAttributes()->getAwGiftcardCodes()) {
            foreach ($quoteGiftcards as $quoteGiftcard) {
                if ($quoteGiftcard->getGiftcardCode() === $giftData['gift_code']) {
                    $quoteGiftcard->setIsRemove(true);
                }
            }
        }
    }

    public function getRefundToGCProductId()
    {
        if (is_null($this->refundToGCProductId)) {
            /** @var \Magento\Catalog\Modelf\Product $productModel */
            $productModel = $this->objectManager->create('Magento\Catalog\Model\Product');
            $this->refundToGCProductId = $productModel->getResource()->getIdBySku(self::AHW_REFUND_TO_GC_SKU);

            if (!$this->refundToGCProductId) {
                $this->refundToGCProductId = $this->createRefundToGCProduct()->getId();
            }
        }

        return $this->refundToGCProductId;
    }

    public function createRefundToGCProduct()
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $this->objectManager->create('Magento\Catalog\Model\Product');
        $product->setUrlKey(uniqid("ahw_refund_gc"));
        $product->setName('Ahw_Refund_GiftCard_Product');
        $product->setTypeId(\Aheadworks\Giftcard\Model\Product\Type\Giftcard::TYPE_CODE);
        $product->setStatus(2);
        $product->setAttributeSetId($this->getAttributeSetForRefundToGCProduct());
        $product->setSku(self::AHW_REFUND_TO_GC_SKU);
        $product->setVisibility(4);
        $product->setPrice(0);
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

        $product->setData(ProductAttributeInterface::CODE_AW_GC_TYPE, GiftcardType::VALUE_COMBINED);
        $product->setData(ProductAttributeInterface::CODE_AW_GC_ALLOW_OPEN_AMOUNT, true);
        $product->setData(ProductAttributeInterface::CODE_AW_GC_OPEN_AMOUNT_MIN, 0.01);
        $product->setData(ProductAttributeInterface::CODE_AW_GC_OPEN_AMOUNT_MAX, 999999999999);
        $product->setData(ProductAttributeInterface::CODE_AW_GC_DESCRIPTION, 'refund to giftcard product by connectpos');

        return $product->save();
    }

    /**
     * PERFECT CODE
     *
     * @return int
     */
    public function getAttributeSetForRefundToGCProduct()
    {
        $productEntityTypeId = $this->objectManager->create(
            '\Magento\Eav\Model\Entity\Type'
        )->loadByCode('catalog_product')
            ->getId();
        $eavAttributeSetCollection = $this->objectManager->create(
            '\Magento\Eav\Model\Entity\Attribute\Set'
        )->getCollection();

        // FIXME: We will implement setting for admin select attribute set of customer later.
        $eavAttributeSetCollection->addFieldToFilter('attribute_set_name', 'Default')
            ->addFieldToFilter('entity_type_id', $productEntityTypeId);

        $id = $eavAttributeSetCollection->getFirstItem()->getId();

        return $id;
    }

    public function getGCCodePool()
    {
        /** @var \Aheadworks\Giftcard\Model\ResourceModel\Pool\Collection $collection */
        $collection = $this->objectManager->create(
            'Aheadworks\Giftcard\Model\ResourceModel\Pool\Collection'
        );
        $listCodePool = [];
        foreach ($collection as $item) {
            $listCodePool[] = $item->getData();
        }

        return $listCodePool;
    }

    public function updateRefundToGCProduct($data)
    {
        $productModel = $this->objectManager->create(
            'Magento\Catalog\Model\Product'
        )->load($this->getRefundToGCProductId());

        if ((!isset($data['is_default_codepool_pattern']) || $data['is_default_codepool_pattern'] != true)
            && isset($data['code_pool'])
            && !!$data['code_pool']
            && class_exists('\Aheadworks\Giftcard\Api\Data\ProductAttributeInterface')
        ) {
            $productModel->setData(ProductAttributeInterface::CODE_AW_GC_POOL, $data['code_pool']);
        } else {
            $productModel->unsetData(ProductAttributeInterface::CODE_AW_GC_POOL);
        }
        $productModel->save();
    }
}
