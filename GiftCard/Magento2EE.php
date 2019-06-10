<?php
/**
 * Created by Hung Nguyen - hungnh@smartosc.com
 * Date: 13/08/2018
 * Time: 14:28
 */

namespace SM\Integrate\GiftCard;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GiftCard\Model\Catalog\Product\Type\Giftcard;
use Magento\GiftCard\Model\Giftcard as GiftcardModel;
use Magento\GiftCardImportExport\Model\Import\Product\Type\GiftCard as GiftCardType;
use SM\Integrate\GiftCard\Contract\AbstractGCIntegrate;
use SM\Integrate\GiftCard\Contract\GCIntegrateInterface;
use \Magento\Store\Model\StoreRepository;

class Magento2EE extends AbstractGCIntegrate implements GCIntegrateInterface
{

    const STATUS_DISABLED = 0;

    const STATUS_ENABLED = 1;

    const STATE_AVAILABLE = 0;

    const STATE_USED = 1;

    const STATE_REDEEMED = 2;

    const STATE_EXPIRED = 3;

    const REDEEMABLE = 1;

    const NOT_REDEEMABLE = 0;

    /**#@+
     * Constants defined for keys of array
     */
    const ENTITY_ID = 'entity_id';

    const GIFT_CARDS = 'gift_cards';

    const GIFT_CARDS_AMOUNT = 'gift_cards_amount';

    const BASE_GIFT_CARDS_AMOUNT = 'base_gift_cards_amount';

    const GIFT_CARDS_AMOUNT_USED = 'gift_cards_amount_used';

    const BASE_GIFT_CARDS_AMOUNT_USED = 'base_gift_cards_amount_used';

    /**
     * Gift card id cart key
     *
     * @var string
     */
    const ID = 'i';

    /**
     * Gift card code cart key
     *
     * @var string
     */
    const CODE = 'c';

    /**
     * Gift card amount cart key
     *
     * @var string
     */
    const AMOUNT = 'a';

    /**
     * Gift card base amount cart key
     *
     * @var string
     */
    const BASE_AMOUNT = 'ba';

    /**
     * Gift card authorized cart key
     */
    const AUTHORIZED = 'authorized';

    const GIFT_CARD_REFUND_TO_GC_SKU = 'refund_to_gift_card_m2_ee';

    /**
     * @var
     */
    protected $refundToGCProductId;

    protected $gcRepository;
    /**
     * Gift card account data
     *
     * @var \Magento\GiftCardAccount\Helper\Data
     */
    protected $giftCardAccountData = null;
    /**
     * @var \SM\Integrate\Helper\Data
     */
    protected $integrateHelperData;

    protected $storeManager;
    /**
     * @param StoreRepository      $storeRepository
     */
    protected $storeRepository;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $localeDate;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \SM\Integrate\Helper\Data $integrateHelperData,
        StoreRepository $storeRepository)
    {
        $this->storeManager       = $storeManager;
        $this->integrateHelperData = $integrateHelperData;
        $this->localeDate         = $localeDate;
        $this->storeRepository    = $storeRepository;
        parent::__construct($objectManager);
    }

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

    /**
     * @param $giftcardDatas
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function saveGCDataBeforeQuoteCollect($giftcardDatas)
    {
        $website = $this->storeManager->getStore($this->getQuote()->getStoreId())->getWebsite();

        foreach ($giftcardDatas as $giftData) {
            if (!isset($giftData['gift_code'])) {
                continue;
            } else {
                $giftData['gift_code'] = preg_replace('/\s+/', '', $giftData['gift_code']);
            }

            try {
                $giftcardCode = $giftData['gift_code'];
                $giftcard     = $this->getGiftCardRepository()->loadByCode($giftcardCode);

                if ($this->isValid(true, true, $website, true, $giftcard)) {
                    $cards = $this->getGiftCardAccountHelper()->getCards($this->getQuote());
                    if (!$cards) {
                        $cards = [];
                    } else {
                        foreach ($cards as $one) {
                            if ($one[self::ID] == $giftcard['giftcardaccount_id']) {
                                throw new LocalizedException(
                                    __('This gift card %1 is already in the order.', $giftcard['code'])
                                );
                            }
                        }
                    }
                    $cards[] = [
                        self::ID => $giftcard['giftcardaccount_id'],
                        self::CODE => $giftcard['code'],
                        self::AMOUNT => $giftcard['balance'],
                        self::BASE_AMOUNT => $giftcard['balance'],
                    ];
                    $this->getGiftCardAccountHelper()->setCards($this->getQuote(), $cards);

                    $this->getQuote()->collectTotals()->save();
                }
            } catch (NoSuchEntityException $e) {
                throw new NoSuchEntityException(
                    __('The specified Gift Card code :' .$giftData['gift_code']. ' is not valid')
                );
            }

            if (isset($giftData['is_delete']) && $giftData['is_delete'] === true) {
                $this->removeGiftCard($giftData);
                continue;
            }
        }
        $this->getQuote()->getShippingAddress()->setCollectShippingRates(true);
    }

    /**
     * @return array
     */
    public function getQuoteGCData()
    {
        $quoteListGC = [];
        $quote       = $this->getQuote();
        $address = $quote->getShippingAddress();
        if ($quote->isVirtual()) {
            $address = $quote->getBillingAddress();
        }
        $usedGiftCards = [];
        $usedGiftCards = unserialize($address->getData('used_gift_cards'));

        if (count($usedGiftCards) > 0) {
            foreach ($usedGiftCards as $giftCard) {
                $quoteGcData = [
                    'is_valid'             => true,
                    'gift_code'            => $giftCard['c'],
                    'base_giftcard_amount' => -$giftCard['ba'],
                    'giftcard_amount'      => -$giftCard['a']
                ];
                $quoteListGC[] = $quoteGcData;
            }
        }
        return $quoteListGC;
    }

    /**
     * Check if this gift card is expired at the moment
     *
     * @return bool
     * @throws \Exception
     */
    public function isExpired($giftcard = null)
    {
        if ($giftcard === null) {
            return false;
        }
        if (!$giftcard['date_expires']) {
            return false;
        }
        $timezone = $this->localeDate->getConfigTimezone(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->getQuote()->getStoreId()
        );
        $expirationDate = (new \DateTime($giftcard['date_expires'], new \DateTimeZone($timezone)))->setTime(0, 0, 0);
        $currentDate = (new \DateTime('now', new \DateTimeZone($timezone)))->setTime(0, 0, 0);
        if ($expirationDate < $currentDate) {
            return true;
        }

        return false;
    }

    /**
     * Check all the gift card validity attributes
     *
     * @param bool  $expirationCheck
     * @param bool  $statusCheck
     * @param mixed $websiteCheck
     * @param mixed $balanceCheck
     * @param null  $giftcard
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function isValid(
        $expirationCheck = true,
        $statusCheck = true,
        $websiteCheck = false,
        $balanceCheck = true,
        $giftcard = null
    ) {
        if ($giftcard === null) {
            throw new LocalizedException(__('The specified Gift Card is null'));
        }
        if (!$giftcard['giftcardaccount_id']) {
            throw new LocalizedException(
                __('Please correct the gift card code %1', $giftcard['code'])
            );
        }

        if ($websiteCheck) {
            if ($websiteCheck === true) {
                $websiteCheck = null;
            }
            $website = $this->storeManager->getWebsite($websiteCheck);
            if ($giftcard['website_id'] != $website->getId()) {
                throw new LocalizedException(__('Please correct the gift card code website %1.', $website->getName()));
            }
        }

        if ($statusCheck && $giftcard['status'] != self::STATUS_ENABLED) {
            throw new LocalizedException(__('Gift card code %1 is not enabled.', $giftcard['code']));
        }

        if ($expirationCheck && $this->isExpired($giftcard)) {
            throw new LocalizedException(__('Gift card code %1 is expired.', $giftcard['code']));
        }

        if ($balanceCheck) {
            if ($giftcard['balance'] <= 0) {
                throw new LocalizedException(__('Gift card code %1 has a zero balance.', $giftcard['code']));
            }
            if ($balanceCheck !== true && is_numeric($balanceCheck)) {
                if ($giftcard['balance'] < $balanceCheck) {
                    throw new LocalizedException(
                        __('Gift card code %1 balance is lower than the charged amount.', $giftcard['code'])
                    );
                }
            }
        }

        return true;
    }

    /**
     * @return \Magento\GiftCardAccount\Helper\Data|mixed
     */
    public function getGiftCardAccountHelper()
    {
        if ($this->integrateHelperData->isGiftCardMagento2EE() && is_null($this->giftCardAccountData)) {
            $this->giftCardAccountData = $this->objectManager->create('Magento\GiftCardAccount\Helper\Data');
        }
        return $this->giftCardAccountData;
    }

    /**
     * Remove gift card from quote gift card storage
     *
     * @param $giftData
     *
     * @return $this|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function removeGiftCard($giftData)
    {
        $giftcardCode = $giftData['gift_code'];
        $giftcard     = $this->getGiftCardRepository()->loadByCode($giftcardCode);

        if (!$giftcard['giftcardaccount_id']) {
            throw new LocalizedException(__('Please correct the gift card account code: "%1".', $giftcard['code']));
        }

        $cards = $this->getGiftCardAccountHelper()->getCards($this->getQuote());
        if ($cards) {
            foreach ($cards as $k => $one) {
                if ($one[self::ID] == $giftcard['giftcardaccount_id']) {
                    unset($cards[$k]);
                    $this->getGiftCardAccountHelper()->setCards($this->getQuote(), $cards);

                    $this->getQuote()->collectTotals()->save();
                    return $this;
                }
            }
        }

        throw new LocalizedException(__('This gift card code %1 wasn\'t found in the order.', $giftcard['code']));
    }

    /**
     * @return mixed
     */
    protected function getGiftCardRepository()
    {
        if (is_null($this->gcRepository)) {
            $this->gcRepository = $this->objectManager->create('\Magento\GiftCardAccount\Model\Giftcardaccount');
        }

        return $this->gcRepository;
    }

    /**
     * @return int
     */
    public function getRefundToGCProductId()
    {
        if (is_null($this->refundToGCProductId)) {
            /** @var \Magento\Catalog\Model\Product $productModel */
            $productModel              = $this->objectManager->create('Magento\Catalog\Model\Product');
            $this->refundToGCProductId = $productModel->getResource()->getIdBySku(self::GIFT_CARD_REFUND_TO_GC_SKU);

            if (!$this->refundToGCProductId) {
                $this->refundToGCProductId = $this->createRefundToGCProduct()->getId();
            }
        }

        return $this->refundToGCProductId;
    }

    /**
     * @return \Magento\Catalog\Model\Product
     * @throws \Exception
     */
    private function createRefundToGCProduct()
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $this->objectManager->create('Magento\Catalog\Model\Product');
        $product->setUrlKey(uniqid("m2ee_refund_giftcard"));
        $product->setName('Refund GiftCard M2EE Product');
        $product->setTypeId(Giftcard::TYPE_GIFTCARD);
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

        $product->setData(GiftCardType::GIFTCARD_TYPE_COLUMN, GiftcardModel::TYPE_COMBINED);
        $product->setData(GiftCardType::ALLOW_OPEN_AMOUNT_COLUMN, GiftcardModel::OPEN_AMOUNT_ENABLED);
        $product->setData(GiftCardType::OPEN_AMOUNT_MIN_COLUMN, 0.01);
        $product->setData(GiftCardType::OPEN_AMOUNT_MAX_COLUMN, 99999);
        return $product->save();
    }

    /**
     * PERFECT CODE
     *
     * @return int
     */
    private function getAttributeSetForRefundToGCProduct()
    {
        $productEntityTypeId = $this->objectManager->create('\Magento\Eav\Model\Entity\Type')
                                                   ->loadByCode('catalog_product')
                                                   ->getId();
        $eavAttributeSetCollection = $this->objectManager->create('\Magento\Eav\Model\Entity\Attribute\Set')
                                                         ->getCollection();

        // FIXME: We will implement setting for admin select attribute set of customer later.
        $eavAttributeSetCollection->addFieldToFilter('attribute_set_name', 'Default')
                                  ->addFieldToFilter('entity_type_id', $productEntityTypeId);

        $id = $eavAttributeSetCollection->getFirstItem()
                                        ->getId();

        if (is_null($id)) {
            $eavAttributeSetCollection = $this->entityAttrSet->getCollection();

            return $eavAttributeSetCollection->addFieldToFilter('entity_type_id', $productEntityTypeId)
                                             ->getFirstItem()
                                             ->getId();
        }
        return $id;
    }

    public function getGCCodePool()
    {
        // TODO: Implement getGCCodePool() method.
    }

    public function updateRefundToGCProduct($data)
    {
        // TODO: Implement updateRefundToGCProduct() method.
    }
}
