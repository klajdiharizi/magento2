<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Sales\Model;

use Magento\Directory\Model\Currency;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order\Address\Collection;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection as CreditmemoCollection;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use Magento\Sales\Model\ResourceModel\Order\Item\Collection as ImportCollection;
use Magento\Sales\Model\ResourceModel\Order\Payment\Collection as PaymentCollection;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Collection as ShipmentCollection;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track\Collection as TrackCollection;
use Magento\Sales\Model\ResourceModel\Order\Status\History\Collection as HistoryCollection;

/**
 * Order model
 *
 * Supported events:
 *  sales_order_load_after
 *  sales_order_save_before
 *  sales_order_save_after
 *  sales_order_delete_before
 *  sales_order_delete_after
 *
 * @method \Magento\Sales\Model\ResourceModel\Order _getResource()
 * @method \Magento\Sales\Model\ResourceModel\Order getResource()
 * @method int getGiftMessageId()
 * @method \Magento\Sales\Model\Order setGiftMessageId(int $value)
 * @method bool hasBillingAddressId()
 * @method \Magento\Sales\Model\Order unsBillingAddressId()
 * @method bool hasShippingAddressId()
 * @method \Magento\Sales\Model\Order unsShippingAddressId()
 * @method int getShippigAddressId()
 * @method bool hasCustomerNoteNotify()
 * @method bool hasForcedCanCreditmemo()
 * @method bool getIsInProcess()
 * @method \Magento\Customer\Model\Customer getCustomer()
 * @method \Magento\Sales\Model\Order setSendEmail(bool $value)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Order extends AbstractModel implements EntityInterface, OrderInterface
{
    const ENTITY = 'order';

    /**
     * Order states
     */
    const STATE_NEW = 'new';

    const STATE_PENDING_PAYMENT = 'pending_payment';

    const STATE_PROCESSING = 'processing';

    const STATE_COMPLETE = 'complete';

    const STATE_CLOSED = 'closed';

    const STATE_CANCELED = 'canceled';

    const STATE_HOLDED = 'holded';

    const STATE_PAYMENT_REVIEW = 'payment_review';

    /**
     * Order statuses
     */
    const STATUS_FRAUD = 'fraud';

    /**
     * Order flags
     */
    const ACTION_FLAG_CANCEL = 'cancel';

    const ACTION_FLAG_HOLD = 'hold';

    const ACTION_FLAG_UNHOLD = 'unhold';

    const ACTION_FLAG_EDIT = 'edit';

    const ACTION_FLAG_CREDITMEMO = 'creditmemo';

    const ACTION_FLAG_INVOICE = 'invoice';

    const ACTION_FLAG_REORDER = 'reorder';

    const ACTION_FLAG_SHIP = 'ship';

    const ACTION_FLAG_COMMENT = 'comment';

    /**
     * Report date types
     */
    const REPORT_DATE_TYPE_CREATED = 'created';

    const REPORT_DATE_TYPE_UPDATED = 'updated';

    /**
     * @var string
     */
    protected $_eventPrefix = 'sales_order';

    /**
     * @var string
     */
    protected $_eventObject = 'order';

    /**
     * @var InvoiceCollection
     */
    protected $_invoices;

    /**
     * @var TrackCollection
     */
    protected $_tracks;

    /**
     * @var ShipmentCollection
     */
    protected $_shipments;

    /**
     * @var CreditmemoCollection
     */
    protected $_creditmemos;

    /**
     * @var array
     */
    protected $_relatedObjects = [];

    /**
     * @var Currency
     */
    protected $_orderCurrency = null;

    /**
     * @var Currency|null
     */
    protected $_baseCurrency = null;

    /**
     * Array of action flags for canUnhold, canEdit, etc.
     *
     * @var array
     */
    protected $_actionFlag = [];

    /**
     * Flag: if after order placing we can send new email to the customer.
     *
     * @var bool
     */
    protected $_canSendNewEmailFlag = true;

    /**
     * Identifier for history item
     *
     * @var string
     */
    protected $entityType = 'order';

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Sales\Model\Order\Config
     */
    protected $_orderConfig;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productListFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory
     */
    protected $_orderItemCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\Product\Visibility
     */
    protected $_productVisibility;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceManagement;

    /**
     * @var \Magento\Directory\Model\CurrencyFactory
     */
    protected $_currencyFactory;

    /**
     * @var \Magento\Sales\Model\Order\Status\HistoryFactory
     */
    protected $_orderHistoryFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Address\CollectionFactory
     */
    protected $_addressCollectionFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Payment\CollectionFactory
     */
    protected $_paymentCollectionFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Status\History\CollectionFactory
     */
    protected $_historyCollectionFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory
     */
    protected $_invoiceCollectionFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory
     */
    protected $_shipmentCollectionFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Creditmemo\CollectionFactory
     */
    protected $_memoCollectionFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Shipment\Track\CollectionFactory
     */
    protected $_trackCollectionFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $salesOrderCollectionFactory;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $timezone;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param Order\Config $orderConfig
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory $orderItemCollectionFactory
     * @param \Magento\Catalog\Model\Product\Visibility $productVisibility
     * @param \Magento\Sales\Api\InvoiceManagementInterface $invoiceManagement
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param Order\Status\HistoryFactory $orderHistoryFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\Address\CollectionFactory $addressCollectionFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\Payment\CollectionFactory $paymentCollectionFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\Status\History\CollectionFactory $historyCollectionFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory $shipmentCollectionFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\Creditmemo\CollectionFactory $memoCollectionFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\Shipment\Track\CollectionFactory $trackCollectionFactory
     * @param ResourceModel\Order\CollectionFactory $salesOrderCollectionFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productListFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory $orderItemCollectionFactory,
        \Magento\Catalog\Model\Product\Visibility $productVisibility,
        \Magento\Sales\Api\InvoiceManagementInterface $invoiceManagement,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory,
        \Magento\Sales\Model\ResourceModel\Order\Address\CollectionFactory $addressCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Payment\CollectionFactory $paymentCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Status\History\CollectionFactory $historyCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory $shipmentCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Creditmemo\CollectionFactory $memoCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Shipment\Track\CollectionFactory $trackCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $salesOrderCollectionFactory,
        PriceCurrencyInterface $priceCurrency,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productListFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_storeManager = $storeManager;
        $this->_orderConfig = $orderConfig;
        $this->productRepository = $productRepository;
        $this->productListFactory = $productListFactory;
        $this->timezone = $timezone;
        $this->_orderItemCollectionFactory = $orderItemCollectionFactory;
        $this->_productVisibility = $productVisibility;
        $this->invoiceManagement = $invoiceManagement;
        $this->_currencyFactory = $currencyFactory;
        $this->_eavConfig = $eavConfig;
        $this->_orderHistoryFactory = $orderHistoryFactory;
        $this->_addressCollectionFactory = $addressCollectionFactory;
        $this->_paymentCollectionFactory = $paymentCollectionFactory;
        $this->_historyCollectionFactory = $historyCollectionFactory;
        $this->_invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->_shipmentCollectionFactory = $shipmentCollectionFactory;
        $this->_memoCollectionFactory = $memoCollectionFactory;
        $this->_trackCollectionFactory = $trackCollectionFactory;
        $this->salesOrderCollectionFactory = $salesOrderCollectionFactory;
        $this->priceCurrency = $priceCurrency;
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Magento\Sales\Model\ResourceModel\Order');
    }

    /**
     * Clear order object data
     *
     * @param string $key data key
     * @return $this
     */
    public function unsetData($key = null)
    {
        parent::unsetData($key);
        if ($key === null) {
            $this->setItems(null);
        }
        return $this;
    }

    /**
     * Retrieve can flag for action (edit, unhold, etc..)
     *
     * @param string $action
     * @return boolean|null
     */
    public function getActionFlag($action)
    {
        if (isset($this->_actionFlag[$action])) {
            return $this->_actionFlag[$action];
        }
        return null;
    }

    /**
     * Set can flag value for action (edit, unhold, etc...)
     *
     * @param string $action
     * @param boolean $flag
     * @return $this
     */
    public function setActionFlag($action, $flag)
    {
        $this->_actionFlag[$action] = (bool)$flag;
        return $this;
    }

    /**
     * Return flag for order if it can sends new email to customer.
     *
     * @return bool
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getCanSendNewEmailFlag()
    {
        return $this->_canSendNewEmailFlag;
    }

    /**
     * Set flag for order if it can sends new email to customer.
     *
     * @param bool $flag
     * @return $this
     */
    public function setCanSendNewEmailFlag($flag)
    {
        $this->_canSendNewEmailFlag = (bool)$flag;
        return $this;
    }

    /**
     * Load order by system increment identifier
     *
     * @param string $incrementId
     * @return \Magento\Sales\Model\Order
     */
    public function loadByIncrementId($incrementId)
    {
        return $this->loadByAttribute('increment_id', $incrementId);
    }

    /**
     * Load order by system increment and store identifiers
     *
     * @param string $incrementId
     * @param string $storeId
     * @return \Magento\Sales\Model\Order
     */
    public function loadByIncrementIdAndStoreId($incrementId, $storeId)
    {
        $orderCollection = $this->getSalesOrderCollection(
            [
                'increment_id' => $incrementId,
                'store_id' => $storeId
            ]
        );
        return $orderCollection->getFirstItem();
    }

    /**
     * Get sales Order collection model populated with data
     *
     * @param array $filters
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection
     */
    protected function getSalesOrderCollection(array $filters = [])
    {
        /** @var \Magento\Sales\Model\ResourceModel\Order\Collection $salesOrderCollection */
        $salesOrderCollection = $this->salesOrderCollectionFactory->create();
        foreach ($filters as $field => $condition) {
            $salesOrderCollection->addFieldToFilter($field, $condition);
        }
        return $salesOrderCollection->load();
    }

    /**
     * Load order by custom attribute value. Attribute value should be unique
     *
     * @param string $attribute
     * @param string $value
     * @return $this
     */
    public function loadByAttribute($attribute, $value)
    {
        $this->load($value, $attribute);
        return $this;
    }

    /**
     * Retrieve store model instance
     *
     * @return \Magento\Store\Model\Store
     */
    public function getStore()
    {
        $storeId = $this->getStoreId();
        if ($storeId) {
            return $this->_storeManager->getStore($storeId);
        }
        return $this->_storeManager->getStore();
    }

    /**
     * Retrieve order cancel availability
     *
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function canCancel()
    {
        if (!$this->_canVoidOrder()) {
            return false;
        }
        if ($this->canUnhold()) {
            return false;
        }
        if (!$this->canReviewPayment() && $this->canFetchPaymentReviewUpdate()) {
            return false;
        }

        $allInvoiced = true;
        foreach ($this->getAllItems() as $item) {
            if ($item->getQtyToInvoice()) {
                $allInvoiced = false;
                break;
            }
        }
        if ($allInvoiced) {
            return false;
        }

        $state = $this->getState();
        if ($this->isCanceled() || $state === self::STATE_COMPLETE || $state === self::STATE_CLOSED) {
            return false;
        }

        if ($this->getActionFlag(self::ACTION_FLAG_CANCEL) === false) {
            return false;
        }

        return true;
    }

    /**
     * Getter whether the payment can be voided
     * @return bool
     */
    public function canVoidPayment()
    {
        return $this->_canVoidOrder() ? $this->getPayment()->canVoid() : false;
    }

    /**
     * Check whether order could be canceled by states and flags
     *
     * @return bool
     */
    protected function _canVoidOrder()
    {
        return !($this->isCanceled() || $this->canUnhold() || $this->isPaymentReview());
    }

    /**
     * Retrieve order invoice availability
     *
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function canInvoice()
    {
        if ($this->canUnhold() || $this->isPaymentReview()) {
            return false;
        }
        $state = $this->getState();
        if ($this->isCanceled() || $state === self::STATE_COMPLETE || $state === self::STATE_CLOSED) {
            return false;
        }

        if ($this->getActionFlag(self::ACTION_FLAG_INVOICE) === false) {
            return false;
        }

        foreach ($this->getAllItems() as $item) {
            if ($item->getQtyToInvoice() > 0 && !$item->getLockedDoInvoice()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieve order credit memo (refund) availability
     *
     * @return bool
     */
    public function canCreditmemo()
    {
        if ($this->hasForcedCanCreditmemo()) {
            return $this->getForcedCanCreditmemo();
        }

        if ($this->canUnhold() || $this->isPaymentReview()) {
            return false;
        }

        if ($this->isCanceled() || $this->getState() === self::STATE_CLOSED) {
            return false;
        }

        /**
         * We can have problem with float in php (on some server $a=762.73;$b=762.73; $a-$b!=0)
         * for this we have additional diapason for 0
         * TotalPaid - contains amount, that were not rounded.
         */
        if (abs($this->priceCurrency->round($this->getTotalPaid()) - $this->getTotalRefunded()) < .0001) {
            return false;
        }

        if ($this->getActionFlag(self::ACTION_FLAG_EDIT) === false) {
            return false;
        }
        return true;
    }

    /**
     * Retrieve order hold availability
     *
     * @return bool
     */
    public function canHold()
    {
        $notHoldableStates = [
            self::STATE_CANCELED,
            self::STATE_PAYMENT_REVIEW,
            self::STATE_COMPLETE,
            self::STATE_CLOSED,
            self::STATE_HOLDED
        ];
        if (in_array($this->getState(), $notHoldableStates)) {
            return false;
        }

        if ($this->getActionFlag(self::ACTION_FLAG_HOLD) === false) {
            return false;
        }
        return true;
    }

    /**
     * Retrieve order unhold availability
     *
     * @return bool
     */
    public function canUnhold()
    {
        if ($this->getActionFlag(self::ACTION_FLAG_UNHOLD) === false || $this->isPaymentReview()) {
            return false;
        }
        return $this->getState() === self::STATE_HOLDED;
    }

    /**
     * Check if comment can be added to order history
     *
     * @return bool
     */
    public function canComment()
    {
        if ($this->getActionFlag(self::ACTION_FLAG_COMMENT) === false) {
            return false;
        }
        return true;
    }

    /**
     * Retrieve order shipment availability
     *
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function canShip()
    {
        if ($this->canUnhold() || $this->isPaymentReview()) {
            return false;
        }

        if ($this->getIsVirtual() || $this->isCanceled()) {
            return false;
        }

        if ($this->getActionFlag(self::ACTION_FLAG_SHIP) === false) {
            return false;
        }

        foreach ($this->getAllItems() as $item) {
            if ($item->getQtyToShip() > 0 && !$item->getIsVirtual() && !$item->getLockedDoShip()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieve order edit availability
     *
     * @return bool
     */
    public function canEdit()
    {
        if ($this->canUnhold()) {
            return false;
        }

        $state = $this->getState();
        if ($this->isCanceled() ||
            $this->isPaymentReview() ||
            $state === self::STATE_COMPLETE ||
            $state === self::STATE_CLOSED
        ) {
            return false;
        }

        if ($this->hasInvoices()) {
            return false;
        }

        if (!$this->getPayment()->getMethodInstance()->canEdit()) {
            return false;
        }

        if ($this->getActionFlag(self::ACTION_FLAG_EDIT) === false) {
            return false;
        }

        return true;
    }

    /**
     * Retrieve order reorder availability
     *
     * @return bool
     */
    public function canReorder()
    {
        return $this->_canReorder(false);
    }

    /**
     * Check the ability to reorder ignoring the availability in stock or status of the ordered products
     *
     * @return bool
     */
    public function canReorderIgnoreSalable()
    {
        return $this->_canReorder(true);
    }

    /**
     * Retrieve order reorder availability
     *
     * @param bool $ignoreSalable
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _canReorder($ignoreSalable = false)
    {
        if ($this->canUnhold() || $this->isPaymentReview() || !$this->getCustomerId()) {
            return false;
        }

        if ($this->getActionFlag(self::ACTION_FLAG_REORDER) === false) {
            return false;
        }

        $products = [];
        foreach ($this->getItemsCollection() as $item) {
            $products[] = $item->getProductId();
        }

        if (!empty($products)) {
            /*
             * @TODO ACPAOC: Use product collection here, but ensure that product
             * is loaded with order store id, otherwise there'll be problems with isSalable()
             * for composite products
             *
             */
            /*
            $productsCollection = $this->_productFactory->create()->getCollection()
                ->setStoreId($this->getStoreId())
                ->addIdFilter($products)
                ->addAttributeToSelect('status')
                ->load();

            foreach ($productsCollection as $product) {
                if (!$product->isSalable()) {
                    return false;
                }
            }
            */

            foreach ($products as $productId) {
                try {
                    $product = $this->productRepository->getById($productId, false, $this->getStoreId());
                    if (!$ignoreSalable && !$product->isSalable()) {
                        return false;
                    }
                } catch (NoSuchEntityException $noEntityException) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check whether the payment is in payment review state
     * In this state order cannot be normally processed. Possible actions can be:
     * - accept or deny payment
     * - fetch transaction information
     *
     * @return bool
     */
    public function isPaymentReview()
    {
        return $this->getState() === self::STATE_PAYMENT_REVIEW;
    }

    /**
     * Check whether payment can be accepted or denied
     *
     * @return bool
     */
    public function canReviewPayment()
    {
        return $this->isPaymentReview() && $this->getPayment()->canReviewPayment();
    }

    /**
     * Check whether there can be a transaction update fetched for payment in review state
     *
     * @return bool
     */
    public function canFetchPaymentReviewUpdate()
    {
        return $this->isPaymentReview() && $this->getPayment()->canFetchTransactionInfo();
    }

    /**
     * Retrieve order configuration model
     *
     * @return \Magento\Sales\Model\Order\Config
     */
    public function getConfig()
    {
        return $this->_orderConfig;
    }

    /**
     * Place order payments
     *
     * @return $this
     */
    protected function _placePayment()
    {
        $this->getPayment()->place();
        return $this;
    }

    /**
     * Retrieve order payment model object
     *
     * @return Payment|false
     */
    public function getPayment()
    {
        foreach ($this->getPayments() as $payment) {
            if (!$payment->isDeleted()) {
                $payment->setOrder($this);
                return $payment;
            }
        }
        return false;
    }

    /**
     * Sets the billing address, if any, for the order.
     *
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $address
     * @return $this
     */
    public function setBillingAddress(\Magento\Sales\Api\Data\OrderAddressInterface $address = null)
    {
        $old = $this->getBillingAddress();
        if (!empty($old) && !empty($address)) {
            $address->setId($old->getId());
        }

        if (!empty($address)) {
            $address->setEmail($this->getCustomerEmail());
            $this->addAddress($address->setAddressType('billing'));
        }
        return $this;
    }

    /**
     * Declare order shipping address
     *
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $address
     * @return $this
     */
    public function setShippingAddress(\Magento\Sales\Api\Data\OrderAddressInterface $address = null)
    {
        $old = $this->getShippingAddress();
        if (!empty($old) && !empty($address)) {
            $address->setId($old->getId());
        }

        if (!empty($address)) {
            $address->setEmail($this->getCustomerEmail());
            $this->addAddress($address->setAddressType('shipping'));
        }
        return $this;
    }

    /**
     * Retrieve order billing address
     *
     * @return \Magento\Sales\Model\Order\Address|null
     */
    public function getBillingAddress()
    {
        foreach ($this->getAddresses() as $address) {
            if ($address->getAddressType() == 'billing' && !$address->isDeleted()) {
                return $address;
            }
        }
        return null;
    }

    /**
     * Retrieve order shipping address
     *
     * @return \Magento\Sales\Model\Order\Address|null
     */
    public function getShippingAddress()
    {
        foreach ($this->getAddresses() as $address) {
            if ($address->getAddressType() == 'shipping' && !$address->isDeleted()) {
                return $address;
            }
        }
        return null;
    }

    /**
     * Set order state
     *
     * @param string $state
     * @return $this
     */
    public function setState($state)
    {
        return $this->setData(self::STATE, $state);
    }

    /**
     * Retrieve label of order status
     *
     * @return string
     */
    public function getStatusLabel()
    {
        return $this->getConfig()->getStatusLabel($this->getStatus());
    }

    /**
     * Add status change information to history
     *
     * @param  string $status
     * @param  string $comment
     * @param  bool $isCustomerNotified
     * @return $this
     */
    public function addStatusToHistory($status, $comment = '', $isCustomerNotified = false)
    {
        $this->addStatusHistoryComment($comment, $status)->setIsCustomerNotified($isCustomerNotified);
        return $this;
    }

    /**
     * Add a comment to order
     * Different or default status may be specified
     *
     * @param string $comment
     * @param bool|string $status
     * @return OrderStatusHistoryInterface
     */
    public function addStatusHistoryComment($comment, $status = false)
    {
        if (false === $status) {
            $status = $this->getStatus();
        } elseif (true === $status) {
            $status = $this->getConfig()->getStateDefaultStatus($this->getState());
        } else {
            $this->setStatus($status);
        }
        $history = $this->_orderHistoryFactory->create()->setStatus(
            $status
        )->setComment(
            $comment
        )->setEntityName(
            $this->entityType
        );
        $this->addStatusHistory($history);
        return $history;
    }

    /**
     * Overrides entity id, which will be saved to comments history status
     *
     * @param string $entityName
     * @return $this
     */
    public function setHistoryEntityName($entityName)
    {
        $this->entityType = $entityName;
        return $this;
    }

    /**
     * Return order entity type
     *
     * @return string
     */
    public function getEntityType()
    {
        return $this->entityType;
    }

    /**
     * Place order
     *
     * @return $this
     */
    public function place()
    {
        $this->_eventManager->dispatch('sales_order_place_before', ['order' => $this]);
        $this->_placePayment();
        $this->_eventManager->dispatch('sales_order_place_after', ['order' => $this]);
        return $this;
    }

    /**
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function hold()
    {
        if (!$this->canHold()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('A hold action is not available.'));
        }
        $this->setHoldBeforeState($this->getState());
        $this->setHoldBeforeStatus($this->getStatus());
        $this->setState(self::STATE_HOLDED)
            ->setStatus($this->getConfig()->getStateDefaultStatus(self::STATE_HOLDED));
        return $this;
    }

    /**
     * Attempt to unhold the order
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function unhold()
    {
        if (!$this->canUnhold()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('You cannot remove the hold.'));
        }

        $this->setState($this->getHoldBeforeState())
            ->setStatus($this->getHoldBeforeStatus());
        $this->setHoldBeforeState(null);
        $this->setHoldBeforeStatus(null);
        return $this;
    }

    /**
     * Cancel order
     *
     * @return $this
     */
    public function cancel()
    {
        if ($this->canCancel()) {
            $this->getPayment()->cancel();
            $this->registerCancellation();

            $this->_eventManager->dispatch('order_cancel_after', ['order' => $this]);
        }

        return $this;
    }

    /**
     * Is order status in DB "Fraud detected"
     *
     * @return bool
     */
    public function isFraudDetected()
    {
        return $this->getOrigData(self::STATE) == self::STATE_PAYMENT_REVIEW
            && $this->getOrigData(self::STATUS) == self::STATUS_FRAUD;
    }

    /**
     * Prepare order totals to cancellation
     *
     * @param string $comment
     * @param bool $graceful
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function registerCancellation($comment = '', $graceful = true)
    {
        if ($this->canCancel() || $this->isPaymentReview() || $this->isFraudDetected()) {
            $state = self::STATE_CANCELED;
            foreach ($this->getAllItems() as $item) {
                if ($state != self::STATE_PROCESSING && $item->getQtyToRefund()) {
                    if ($item->getQtyToShip() > $item->getQtyToCancel()) {
                        $state = self::STATE_PROCESSING;
                    } else {
                        $state = self::STATE_COMPLETE;
                    }
                }
                $item->cancel();
            }

            $this->setSubtotalCanceled($this->getSubtotal() - $this->getSubtotalInvoiced());
            $this->setBaseSubtotalCanceled($this->getBaseSubtotal() - $this->getBaseSubtotalInvoiced());

            $this->setTaxCanceled($this->getTaxAmount() - $this->getTaxInvoiced());
            $this->setBaseTaxCanceled($this->getBaseTaxAmount() - $this->getBaseTaxInvoiced());

            $this->setShippingCanceled($this->getShippingAmount() - $this->getShippingInvoiced());
            $this->setBaseShippingCanceled($this->getBaseShippingAmount() - $this->getBaseShippingInvoiced());

            $this->setDiscountCanceled(abs($this->getDiscountAmount()) - $this->getDiscountInvoiced());
            $this->setBaseDiscountCanceled(abs($this->getBaseDiscountAmount()) - $this->getBaseDiscountInvoiced());

            $this->setTotalCanceled($this->getGrandTotal() - $this->getTotalPaid());
            $this->setBaseTotalCanceled($this->getBaseGrandTotal() - $this->getBaseTotalPaid());

            $this->setState($state)
                ->setStatus($this->getConfig()->getStateDefaultStatus($state));
            if (!empty($comment)) {
                $this->addStatusHistoryComment($comment, false);
            }
        } elseif (!$graceful) {
            throw new \Magento\Framework\Exception\LocalizedException(__('We cannot cancel this order.'));
        }
        return $this;
    }

    /**
     * Retrieve tracking numbers
     *
     * @return array
     */
    public function getTrackingNumbers()
    {
        if ($this->getData('tracking_numbers')) {
            return explode(',', $this->getData('tracking_numbers'));
        }
        return [];
    }

    /**
     * Retrieve shipping method
     *
     * @param bool $asObject return carrier code and shipping method data as object
     * @return string|\Magento\Framework\DataObject
     */
    public function getShippingMethod($asObject = false)
    {
        $shippingMethod = parent::getShippingMethod();
        if (!$asObject) {
            return $shippingMethod;
        } else {
            list($carrierCode, $method) = explode('_', $shippingMethod, 2);
            return new \Magento\Framework\DataObject(['carrier_code' => $carrierCode, 'method' => $method]);
        }
    }

    /*********************** ADDRESSES ***************************/

    /**
     * @return Collection
     */
    public function getAddressesCollection()
    {
        $collection = $this->_addressCollectionFactory->create()->setOrderFilter($this);
        if ($this->getId()) {
            foreach ($collection as $address) {
                $address->setOrder($this);
            }
        }
        return $collection;
    }

    /**
     * @param mixed $addressId
     * @return false
     */
    public function getAddressById($addressId)
    {
        foreach ($this->getAddressesCollection() as $address) {
            if ($address->getId() == $addressId) {
                return $address;
            }
        }
        return false;
    }

    /**
     * @param \Magento\Sales\Model\Order\Address $address
     * @return $this
     */
    public function addAddress(\Magento\Sales\Model\Order\Address $address)
    {
        $address->setOrder($this)->setParentId($this->getId());
        if (!$address->getId()) {
            $this->setAddresses(array_merge($this->getAddresses(), [$address]));
            $this->setDataChanges(true);
        }
        return $this;
    }

    /**
     * @param array $filterByTypes
     * @param bool $nonChildrenOnly
     * @return ImportCollection
     */
    public function getItemsCollection($filterByTypes = [], $nonChildrenOnly = false)
    {
        $collection = $this->_orderItemCollectionFactory->create()->setOrderFilter($this);

        if ($filterByTypes) {
            $collection->filterByTypes($filterByTypes);
        }
        if ($nonChildrenOnly) {
            $collection->filterByParent();
        }

        if ($this->getId()) {
            foreach ($collection as $item) {
                $item->setOrder($this);
            }
        }
        return $collection;
    }

    /**
     * Get random items collection without related children
     *
     * @param int $limit
     * @return ImportCollection
     */
    public function getParentItemsRandomCollection($limit = 1)
    {
        return $this->_getItemsRandomCollection($limit, true);
    }

    /**
     * Get random items collection with or without related children
     *
     * @param int $limit
     * @param bool $nonChildrenOnly
     * @return ImportCollection
     */
    protected function _getItemsRandomCollection($limit, $nonChildrenOnly = false)
    {
        $collection = $this->_orderItemCollectionFactory->create()->setOrderFilter($this)->setRandomOrder();

        if ($nonChildrenOnly) {
            $collection->filterByParent();
        }
        $products = [];
        foreach ($collection as $item) {
            $products[] = $item->getProductId();
        }

        $productsCollection = $this->productListFactory->create()->addIdFilter(
            $products
        )->setVisibility(
            $this->_productVisibility->getVisibleInSiteIds()
        )->addPriceData()->setPageSize(
            $limit
        )->load();

        foreach ($collection as $item) {
            $product = $productsCollection->getItemById($item->getProductId());
            if ($product) {
                $item->setProduct($product);
            }
        }

        return $collection;
    }

    /**
     * @return \Magento\Sales\Model\Order\Item[]
     */
    public function getAllItems()
    {
        $items = [];
        foreach ($this->getItems() as $item) {
            if (!$item->isDeleted()) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * @return array
     */
    public function getAllVisibleItems()
    {
        $items = [];
        foreach ($this->getItems() as $item) {
            if (!$item->isDeleted() && !$item->getParentItemId()) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * Gets order item by given ID.
     *
     * @param int $itemId
     * @return \Magento\Framework\DataObject|null
     */
    public function getItemById($itemId)
    {
        $items = $this->getItems();

        if (isset($items[$itemId])) {
            return $items[$itemId];
        }

        return null;
    }

    /**
     * @param mixed $quoteItemId
     * @return  \Magento\Framework\DataObject|null
     */
    public function getItemByQuoteItemId($quoteItemId)
    {
        foreach ($this->getItems() as $item) {
            if ($item->getQuoteItemId() == $quoteItemId) {
                return $item;
            }
        }
        return null;
    }

    /**
     * @param \Magento\Sales\Model\Order\Item $item
     * @return $this
     */
    public function addItem(\Magento\Sales\Model\Order\Item $item)
    {
        $item->setOrder($this);
        if (!$item->getId()) {
            $this->setItems(array_merge($this->getItems(), [$item]));
        }
        return $this;
    }

    /*********************** PAYMENTS ***************************/

    /**
     * @return PaymentCollection
     */
    public function getPaymentsCollection()
    {
        $collection = $this->_paymentCollectionFactory->create()->setOrderFilter($this);
        if ($this->getId()) {
            foreach ($collection as $payment) {
                $payment->setOrder($this);
            }
        }
        return $collection;
    }

    /**
     * @return array
     */
    public function getAllPayments()
    {
        $payments = [];
        foreach ($this->getPaymentsCollection() as $payment) {
            if (!$payment->isDeleted()) {
                $payments[] = $payment;
            }
        }
        return $payments;
    }

    /**
     * @param mixed $paymentId
     * @return Payment|false
     */
    public function getPaymentById($paymentId)
    {
        foreach ($this->getPaymentsCollection() as $payment) {
            if ($payment->getId() == $paymentId) {
                return $payment;
            }
        }
        return false;
    }

    /**
     * @param Payment $payment
     * @return $this
     */
    public function addPayment(Payment $payment)
    {
        $payment->setOrder($this)->setParentId($this->getId());
        if (!$payment->getId()) {
            $this->setPayments(array_merge($this->getPayments(), [$payment]));
            $this->setDataChanges(true);
        }
        return $this;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $payment
     * @return Payment
     */
    public function setPayment(\Magento\Sales\Api\Data\OrderPaymentInterface $payment)
    {
        if (!$this->getIsMultiPayment() && ($previousPayment = $this->getPayment())) {
            $payment->setEntityId($previousPayment->getEntityId());
        }
        $this->addPayment($payment);
        return $payment;
    }

    /*********************** STATUSES ***************************/
    /**
     * Return collection of order status history items.
     *
     * @return HistoryCollection
     */
    public function getStatusHistoryCollection()
    {
        $collection = $this->_historyCollectionFactory->create()->setOrderFilter($this)
            ->setOrder('created_at', 'desc')
            ->setOrder('entity_id', 'desc');
        if ($this->getId()) {
            foreach ($collection as $status) {
                $status->setOrder($this);
            }
        }
        return $collection;
    }

    /**
     * Return array of order status history items without deleted.
     *
     * @return array
     */
    public function getAllStatusHistory()
    {
        $history = [];
        foreach ($this->getStatusHistoryCollection() as $status) {
            if (!$status->isDeleted()) {
                $history[] = $status;
            }
        }
        return $history;
    }

    /**
     * Return collection of visible on frontend order status history items.
     *
     * @return array
     */
    public function getVisibleStatusHistory()
    {
        $history = [];
        foreach ($this->getStatusHistoryCollection() as $status) {
            if (!$status->isDeleted() && $status->getComment() && $status->getIsVisibleOnFront()) {
                $history[] = $status;
            }
        }
        return $history;
    }

    /**
     * @param mixed $statusId
     * @return string|false
     */
    public function getStatusHistoryById($statusId)
    {
        foreach ($this->getStatusHistoryCollection() as $status) {
            if ($status->getId() == $statusId) {
                return $status;
            }
        }
        return false;
    }

    /**
     * Set the order status history object and the order object to each other
     * Adds the object to the status history collection, which is automatically saved when the order is saved.
     * See the entity_id attribute backend model.
     * Or the history record can be saved standalone after this.
     *
     * @param \Magento\Sales\Model\Order\Status\History $history
     * @return $this
     */
    public function addStatusHistory(\Magento\Sales\Model\Order\Status\History $history)
    {
        $history->setOrder($this);
        $this->setStatus($history->getStatus());
        if (!$history->getId()) {
            $this->setStatusHistories(array_merge($this->getStatusHistories(), [$history]));
            $this->setDataChanges(true);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getRealOrderId()
    {
        $id = $this->getData('real_order_id');
        if ($id === null) {
            $id = $this->getIncrementId();
        }
        return $id;
    }

    /**
     * Get currency model instance. Will be used currency with which order placed
     *
     * @return Currency
     */
    public function getOrderCurrency()
    {
        if ($this->_orderCurrency === null) {
            $this->_orderCurrency = $this->_currencyFactory->create();
            $this->_orderCurrency->load($this->getOrderCurrencyCode());
        }
        return $this->_orderCurrency;
    }

    /**
     * Get formatted price value including order currency rate to order website currency
     *
     * @param   float $price
     * @param   bool  $addBrackets
     * @return  string
     */
    public function formatPrice($price, $addBrackets = false)
    {
        return $this->formatPricePrecision($price, 2, $addBrackets);
    }

    /**
     * @param float $price
     * @param int $precision
     * @param bool $addBrackets
     * @return string
     */
    public function formatPricePrecision($price, $precision, $addBrackets = false)
    {
        return $this->getOrderCurrency()->formatPrecision($price, $precision, [], true, $addBrackets);
    }

    /**
     * Retrieve text formatted price value including order rate
     *
     * @param   float $price
     * @return  string
     */
    public function formatPriceTxt($price)
    {
        return $this->getOrderCurrency()->formatTxt($price);
    }

    /**
     * Retrieve order website currency for working with base prices
     *
     * @return Currency
     */
    public function getBaseCurrency()
    {
        if ($this->_baseCurrency === null) {
            $this->_baseCurrency = $this->_currencyFactory->create()->load($this->getBaseCurrencyCode());
        }
        return $this->_baseCurrency;
    }

    /**
     * @param float $price
     * @return string
     */
    public function formatBasePrice($price)
    {
        return $this->formatBasePricePrecision($price, 2);
    }

    /**
     * @param float $price
     * @param int $precision
     * @return string
     */
    public function formatBasePricePrecision($price, $precision)
    {
        return $this->getBaseCurrency()->formatPrecision($price, $precision);
    }

    /**
     * @return bool
     */
    public function isCurrencyDifferent()
    {
        return $this->getOrderCurrencyCode() != $this->getBaseCurrencyCode();
    }

    /**
     * Retrieve order total due value
     *
     * @return float
     */
    public function getTotalDue()
    {
        $total = $this->getGrandTotal() - $this->getTotalPaid();
        $total = $this->priceCurrency->round($total);
        return max($total, 0);
    }

    /**
     * Retrieve order total due value
     *
     * @return float
     */
    public function getBaseTotalDue()
    {
        $total = $this->getBaseGrandTotal() - $this->getBaseTotalPaid();
        $total = $this->priceCurrency->round($total);
        return max($total, 0);
    }

    /**
     * @param string $key
     * @param null|string|int $index
     * @return mixed
     */
    public function getData($key = '', $index = null)
    {
        if ($key == 'total_due') {
            return $this->getTotalDue();
        }
        if ($key == 'base_total_due') {
            return $this->getBaseTotalDue();
        }
        return parent::getData($key, $index);
    }

    /**
     * Retrieve order invoices collection
     *
     * @return InvoiceCollection
     */
    public function getInvoiceCollection()
    {
        if ($this->_invoices === null) {
            $this->_invoices = $this->_invoiceCollectionFactory->create()->setOrderFilter($this);

            if ($this->getId()) {
                foreach ($this->_invoices as $invoice) {
                    $invoice->setOrder($this);
                }
            }
        }
        return $this->_invoices;
    }

    /**
     * Set order invoices collection
     *
     * @param InvoiceCollection $invoices
     * @return $this
     */
    public function setInvoiceCollection(InvoiceCollection $invoices)
    {
        $this->_invoices = $invoices;
        return $this;
    }

    /**
     * Retrieve order shipments collection
     *
     * @return ShipmentCollection|false
     */
    public function getShipmentsCollection()
    {
        if (empty($this->_shipments)) {
            if ($this->getId()) {
                $this->_shipments = $this->_shipmentCollectionFactory->create()->setOrderFilter($this)->load();
            } else {
                return false;
            }
        }
        return $this->_shipments;
    }

    /**
     * Retrieve order creditmemos collection
     *
     * @return CreditmemoCollection|false
     */
    public function getCreditmemosCollection()
    {
        if (empty($this->_creditmemos)) {
            if ($this->getId()) {
                $this->_creditmemos = $this->_memoCollectionFactory->create()->setOrderFilter($this)->load();
            } else {
                return false;
            }
        }
        return $this->_creditmemos;
    }

    /**
     * Retrieve order tracking numbers collection
     *
     * @return TrackCollection
     */
    public function getTracksCollection()
    {
        if (empty($this->_tracks)) {
            $this->_tracks = $this->_trackCollectionFactory->create()->setOrderFilter($this);

            if ($this->getId()) {
                $this->_tracks->load();
            }
        }
        return $this->_tracks;
    }

    /**
     * Check order invoices availability
     *
     * @return bool
     */
    public function hasInvoices()
    {
        return $this->getInvoiceCollection()->count();
    }

    /**
     * Check order shipments availability
     *
     * @return bool
     */
    public function hasShipments()
    {
        return $this->getShipmentsCollection()->count();
    }

    /**
     * Check order creditmemos availability
     *
     * @return bool
     */
    public function hasCreditmemos()
    {
        return $this->getCreditmemosCollection()->count();
    }

    /**
     * Retrieve array of related objects
     *
     * Used for order saving
     *
     * @return array
     */
    public function getRelatedObjects()
    {
        return $this->_relatedObjects;
    }

    /**
     * @return string
     */
    public function getCustomerName()
    {
        if ($this->getCustomerFirstname()) {
            $customerName = $this->getCustomerFirstname() . ' ' . $this->getCustomerLastname();
        } else {
            $customerName = (string)__('Guest');
        }
        return $customerName;
    }

    /**
     * Add New object to related array
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     */
    public function addRelatedObject(\Magento\Framework\Model\AbstractModel $object)
    {
        $this->_relatedObjects[] = $object;
        return $this;
    }

    /**
     * Get formatted order created date in store timezone
     *
     * @param   string $format date format type (short|medium|long|full)
     * @return  string
     */
    public function getCreatedAtFormatted($format)
    {
        return $this->timezone->formatDate(
            $this->timezone->scopeDate(
                $this->getStore(),
                $this->getCreatedAt(),
                true
            ),
            $format,
            true
        );
    }

    /**
     * @return string
     */
    public function getEmailCustomerNote()
    {
        if ($this->getCustomerNoteNotify()) {
            return $this->getCustomerNote();
        }
        return '';
    }

    /**
     * @return string
     */
    public function getStoreGroupName()
    {
        $storeId = $this->getStoreId();
        if ($storeId === null) {
            return $this->getStoreName(1);
        }
        return $this->getStore()->getGroup()->getName();
    }

    /**
     * Resets all data in object
     * so after another load it will be complete new object
     *
     * @return $this
     */
    public function reset()
    {
        $this->unsetData();
        $this->_actionFlag = [];
        $this->setAddresses(null);
        $this->setItems(null);
        $this->setPayments(null);
        $this->setStatusHistories(null);
        $this->_invoices = null;
        $this->_tracks = null;
        $this->_shipments = null;
        $this->_creditmemos = null;
        $this->_relatedObjects = [];
        $this->_orderCurrency = null;
        $this->_baseCurrency = null;

        return $this;
    }

    /**
     * @return bool
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getIsNotVirtual()
    {
        return !$this->getIsVirtual();
    }

    /**
     * Create new invoice with maximum qty for invoice for each item
     *
     * @param array $qtys
     * @return \Magento\Sales\Model\Order\Invoice
     */
    public function prepareInvoice($qtys = [])
    {
        return $this->invoiceManagement->prepareInvoice($this, $qtys);
    }

    /**
     * Check whether order is canceled
     *
     * @return bool
     */
    public function isCanceled()
    {
        return $this->getState() === self::STATE_CANCELED;
    }

    /**
     * Returns increment id
     *
     * @codeCoverageIgnore
     *
     * @return string
     */
    public function getIncrementId()
    {
        return $this->getData('increment_id');
    }

    /**
     * @return \Magento\Sales\Api\Data\OrderItemInterface[]
     */
    public function getItems()
    {
        if ($this->getData(OrderInterface::ITEMS) == null) {
            $this->setData(
                OrderInterface::ITEMS,
                $this->getItemsCollection()->getItems()
            );
        }
        return $this->getData(OrderInterface::ITEMS);
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function setItems($items)
    {
        return $this->setData(OrderInterface::ITEMS, $items);
    }

    /**
     * @return \Magento\Sales\Api\Data\OrderPaymentInterface[]
     */
    public function getPayments()
    {
        if ($this->getData(OrderInterface::PAYMENTS) == null) {
            $this->setData(
                OrderInterface::PAYMENTS,
                $this->getPaymentsCollection()->getItems()
            );
        }
        return $this->getData(OrderInterface::PAYMENTS);
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function setPayments(array $payments = null)
    {
        return $this->setData(OrderInterface::PAYMENTS, $payments);
    }

    /**
     * @return \Magento\Sales\Api\Data\OrderAddressInterface[]
     */
    public function getAddresses()
    {
        if ($this->getData(OrderInterface::ADDRESSES) == null) {
            $this->setData(
                OrderInterface::ADDRESSES,
                $this->getAddressesCollection()->getItems()
            );
        }
        return $this->getData(OrderInterface::ADDRESSES);
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function setAddresses(array $addresses = null)
    {
        return $this->setData(OrderInterface::ADDRESSES, $addresses);
    }

    /**
     * @return \Magento\Sales\Api\Data\OrderStatusHistoryInterface[]
     */
    public function getStatusHistories()
    {
        if ($this->getData(OrderInterface::STATUS_HISTORIES) == null) {
            $this->setData(
                OrderInterface::STATUS_HISTORIES,
                $this->getStatusHistoryCollection()->getItems()
            );
        }
        return $this->getData(OrderInterface::STATUS_HISTORIES);
    }

    /**
     * {@inheritdoc}
     *
     * @return \Magento\Sales\Api\Data\OrderExtensionInterface|null
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * {@inheritdoc}
     *
     * @param \Magento\Sales\Api\Data\OrderExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(\Magento\Sales\Api\Data\OrderExtensionInterface $extensionAttributes)
    {
        return $this->_setExtensionAttributes($extensionAttributes);
    }

    //@codeCoverageIgnoreStart
    /**
     * Returns adjustment_negative
     *
     * @return float
     */
    public function getAdjustmentNegative()
    {
        return $this->getData(OrderInterface::ADJUSTMENT_NEGATIVE);
    }

    /**
     * Returns adjustment_positive
     *
     * @return float
     */
    public function getAdjustmentPositive()
    {
        return $this->getData(OrderInterface::ADJUSTMENT_POSITIVE);
    }

    /**
     * Returns applied_rule_ids
     *
     * @return string
     */
    public function getAppliedRuleIds()
    {
        return $this->getData(OrderInterface::APPLIED_RULE_IDS);
    }

    /**
     * Returns base_adjustment_negative
     *
     * @return float
     */
    public function getBaseAdjustmentNegative()
    {
        return $this->getData(OrderInterface::BASE_ADJUSTMENT_NEGATIVE);
    }

    /**
     * Returns base_adjustment_positive
     *
     * @return float
     */
    public function getBaseAdjustmentPositive()
    {
        return $this->getData(OrderInterface::BASE_ADJUSTMENT_POSITIVE);
    }

    /**
     * Returns base_currency_code
     *
     * @return string
     */
    public function getBaseCurrencyCode()
    {
        return $this->getData(OrderInterface::BASE_CURRENCY_CODE);
    }

    /**
     * Returns base_discount_amount
     *
     * @return float
     */
    public function getBaseDiscountAmount()
    {
        return $this->getData(OrderInterface::BASE_DISCOUNT_AMOUNT);
    }

    /**
     * Returns base_discount_canceled
     *
     * @return float
     */
    public function getBaseDiscountCanceled()
    {
        return $this->getData(OrderInterface::BASE_DISCOUNT_CANCELED);
    }

    /**
     * Returns base_discount_invoiced
     *
     * @return float
     */
    public function getBaseDiscountInvoiced()
    {
        return $this->getData(OrderInterface::BASE_DISCOUNT_INVOICED);
    }

    /**
     * Returns base_discount_refunded
     *
     * @return float
     */
    public function getBaseDiscountRefunded()
    {
        return $this->getData(OrderInterface::BASE_DISCOUNT_REFUNDED);
    }

    /**
     * Returns base_grand_total
     *
     * @return float
     */
    public function getBaseGrandTotal()
    {
        return $this->getData(OrderInterface::BASE_GRAND_TOTAL);
    }

    /**
     * Returns base_discount_tax_compensation_amount
     *
     * @return float
     */
    public function getBaseDiscountTaxCompensationAmount()
    {
        return $this->getData(OrderInterface::BASE_DISCOUNT_TAX_COMPENSATION_AMOUNT);
    }

    /**
     * Returns base_discount_tax_compensation_invoiced
     *
     * @return float
     */
    public function getBaseDiscountTaxCompensationInvoiced()
    {
        return $this->getData(OrderInterface::BASE_DISCOUNT_TAX_COMPENSATION_INVOICED);
    }

    /**
     * Returns base_discount_tax_compensation_refunded
     *
     * @return float
     */
    public function getBaseDiscountTaxCompensationRefunded()
    {
        return $this->getData(OrderInterface::BASE_DISCOUNT_TAX_COMPENSATION_REFUNDED);
    }

    /**
     * Returns base_shipping_amount
     *
     * @return float
     */
    public function getBaseShippingAmount()
    {
        return $this->getData(OrderInterface::BASE_SHIPPING_AMOUNT);
    }

    /**
     * Returns base_shipping_canceled
     *
     * @return float
     */
    public function getBaseShippingCanceled()
    {
        return $this->getData(OrderInterface::BASE_SHIPPING_CANCELED);
    }

    /**
     * Returns base_shipping_discount_amount
     *
     * @return float
     */
    public function getBaseShippingDiscountAmount()
    {
        return $this->getData(OrderInterface::BASE_SHIPPING_DISCOUNT_AMOUNT);
    }

    /**
     * Returns base_shipping_discount_tax_compensation_amnt
     *
     * @return float
     */
    public function getBaseShippingDiscountTaxCompensationAmnt()
    {
        return $this->getData(OrderInterface::BASE_SHIPPING_DISCOUNT_TAX_COMPENSATION_AMNT);
    }

    /**
     * Returns base_shipping_incl_tax
     *
     * @return float
     */
    public function getBaseShippingInclTax()
    {
        return $this->getData(OrderInterface::BASE_SHIPPING_INCL_TAX);
    }

    /**
     * Returns base_shipping_invoiced
     *
     * @return float
     */
    public function getBaseShippingInvoiced()
    {
        return $this->getData(OrderInterface::BASE_SHIPPING_INVOICED);
    }

    /**
     * Returns base_shipping_refunded
     *
     * @return float
     */
    public function getBaseShippingRefunded()
    {
        return $this->getData(OrderInterface::BASE_SHIPPING_REFUNDED);
    }

    /**
     * Returns base_shipping_tax_amount
     *
     * @return float
     */
    public function getBaseShippingTaxAmount()
    {
        return $this->getData(OrderInterface::BASE_SHIPPING_TAX_AMOUNT);
    }

    /**
     * Returns base_shipping_tax_refunded
     *
     * @return float
     */
    public function getBaseShippingTaxRefunded()
    {
        return $this->getData(OrderInterface::BASE_SHIPPING_TAX_REFUNDED);
    }

    /**
     * Returns base_subtotal
     *
     * @return float
     */
    public function getBaseSubtotal()
    {
        return $this->getData(OrderInterface::BASE_SUBTOTAL);
    }

    /**
     * Returns base_subtotal_canceled
     *
     * @return float
     */
    public function getBaseSubtotalCanceled()
    {
        return $this->getData(OrderInterface::BASE_SUBTOTAL_CANCELED);
    }

    /**
     * Returns base_subtotal_incl_tax
     *
     * @return float
     */
    public function getBaseSubtotalInclTax()
    {
        return $this->getData(OrderInterface::BASE_SUBTOTAL_INCL_TAX);
    }

    /**
     * Returns base_subtotal_invoiced
     *
     * @return float
     */
    public function getBaseSubtotalInvoiced()
    {
        return $this->getData(OrderInterface::BASE_SUBTOTAL_INVOICED);
    }

    /**
     * Returns base_subtotal_refunded
     *
     * @return float
     */
    public function getBaseSubtotalRefunded()
    {
        return $this->getData(OrderInterface::BASE_SUBTOTAL_REFUNDED);
    }

    /**
     * Returns base_tax_amount
     *
     * @return float
     */
    public function getBaseTaxAmount()
    {
        return $this->getData(OrderInterface::BASE_TAX_AMOUNT);
    }

    /**
     * Returns base_tax_canceled
     *
     * @return float
     */
    public function getBaseTaxCanceled()
    {
        return $this->getData(OrderInterface::BASE_TAX_CANCELED);
    }

    /**
     * Returns base_tax_invoiced
     *
     * @return float
     */
    public function getBaseTaxInvoiced()
    {
        return $this->getData(OrderInterface::BASE_TAX_INVOICED);
    }

    /**
     * Returns base_tax_refunded
     *
     * @return float
     */
    public function getBaseTaxRefunded()
    {
        return $this->getData(OrderInterface::BASE_TAX_REFUNDED);
    }

    /**
     * Returns base_total_canceled
     *
     * @return float
     */
    public function getBaseTotalCanceled()
    {
        return $this->getData(OrderInterface::BASE_TOTAL_CANCELED);
    }

    /**
     * Returns base_total_invoiced
     *
     * @return float
     */
    public function getBaseTotalInvoiced()
    {
        return $this->getData(OrderInterface::BASE_TOTAL_INVOICED);
    }

    /**
     * Returns base_total_invoiced_cost
     *
     * @return float
     */
    public function getBaseTotalInvoicedCost()
    {
        return $this->getData(OrderInterface::BASE_TOTAL_INVOICED_COST);
    }

    /**
     * Returns base_total_offline_refunded
     *
     * @return float
     */
    public function getBaseTotalOfflineRefunded()
    {
        return $this->getData(OrderInterface::BASE_TOTAL_OFFLINE_REFUNDED);
    }

    /**
     * Returns base_total_online_refunded
     *
     * @return float
     */
    public function getBaseTotalOnlineRefunded()
    {
        return $this->getData(OrderInterface::BASE_TOTAL_ONLINE_REFUNDED);
    }

    /**
     * Returns base_total_paid
     *
     * @return float
     */
    public function getBaseTotalPaid()
    {
        return $this->getData(OrderInterface::BASE_TOTAL_PAID);
    }

    /**
     * Returns base_total_qty_ordered
     *
     * @return float
     */
    public function getBaseTotalQtyOrdered()
    {
        return $this->getData(OrderInterface::BASE_TOTAL_QTY_ORDERED);
    }

    /**
     * Returns base_total_refunded
     *
     * @return float
     */
    public function getBaseTotalRefunded()
    {
        return $this->getData(OrderInterface::BASE_TOTAL_REFUNDED);
    }

    /**
     * Returns base_to_global_rate
     *
     * @return float
     */
    public function getBaseToGlobalRate()
    {
        return $this->getData(OrderInterface::BASE_TO_GLOBAL_RATE);
    }

    /**
     * Returns base_to_order_rate
     *
     * @return float
     */
    public function getBaseToOrderRate()
    {
        return $this->getData(OrderInterface::BASE_TO_ORDER_RATE);
    }

    /**
     * Returns billing_address_id
     *
     * @return int
     */
    public function getBillingAddressId()
    {
        return $this->getData(OrderInterface::BILLING_ADDRESS_ID);
    }

    /**
     * Returns can_ship_partially
     *
     * @return int
     */
    public function getCanShipPartially()
    {
        return $this->getData(OrderInterface::CAN_SHIP_PARTIALLY);
    }

    /**
     * Returns can_ship_partially_item
     *
     * @return int
     */
    public function getCanShipPartiallyItem()
    {
        return $this->getData(OrderInterface::CAN_SHIP_PARTIALLY_ITEM);
    }

    /**
     * Returns coupon_code
     *
     * @return string
     */
    public function getCouponCode()
    {
        return $this->getData(OrderInterface::COUPON_CODE);
    }

    /**
     * Returns created_at
     *
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->getData(OrderInterface::CREATED_AT);
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(OrderInterface::CREATED_AT, $createdAt);
    }

    /**
     * Returns customer_dob
     *
     * @return string
     */
    public function getCustomerDob()
    {
        return $this->getData(OrderInterface::CUSTOMER_DOB);
    }

    /**
     * Returns customer_email
     *
     * @return string
     */
    public function getCustomerEmail()
    {
        return $this->getData(OrderInterface::CUSTOMER_EMAIL);
    }

    /**
     * Returns customer_firstname
     *
     * @return string
     */
    public function getCustomerFirstname()
    {
        return $this->getData(OrderInterface::CUSTOMER_FIRSTNAME);
    }

    /**
     * Returns customer_gender
     *
     * @return int
     */
    public function getCustomerGender()
    {
        return $this->getData(OrderInterface::CUSTOMER_GENDER);
    }

    /**
     * Returns customer_group_id
     *
     * @return int
     */
    public function getCustomerGroupId()
    {
        return $this->getData(OrderInterface::CUSTOMER_GROUP_ID);
    }

    /**
     * Returns customer_id
     *
     * @return int
     */
    public function getCustomerId()
    {
        return $this->getData(OrderInterface::CUSTOMER_ID);
    }

    /**
     * Returns customer_is_guest
     *
     * @return int
     */
    public function getCustomerIsGuest()
    {
        return $this->getData(OrderInterface::CUSTOMER_IS_GUEST);
    }

    /**
     * Returns customer_lastname
     *
     * @return string
     */
    public function getCustomerLastname()
    {
        return $this->getData(OrderInterface::CUSTOMER_LASTNAME);
    }

    /**
     * Returns customer_middlename
     *
     * @return string
     */
    public function getCustomerMiddlename()
    {
        return $this->getData(OrderInterface::CUSTOMER_MIDDLENAME);
    }

    /**
     * Returns customer_note
     *
     * @return string
     */
    public function getCustomerNote()
    {
        return $this->getData(OrderInterface::CUSTOMER_NOTE);
    }

    /**
     * Returns customer_note_notify
     *
     * @return int
     */
    public function getCustomerNoteNotify()
    {
        return $this->getData(OrderInterface::CUSTOMER_NOTE_NOTIFY);
    }

    /**
     * Returns customer_prefix
     *
     * @return string
     */
    public function getCustomerPrefix()
    {
        return $this->getData(OrderInterface::CUSTOMER_PREFIX);
    }

    /**
     * Returns customer_suffix
     *
     * @return string
     */
    public function getCustomerSuffix()
    {
        return $this->getData(OrderInterface::CUSTOMER_SUFFIX);
    }

    /**
     * Returns customer_taxvat
     *
     * @return string
     */
    public function getCustomerTaxvat()
    {
        return $this->getData(OrderInterface::CUSTOMER_TAXVAT);
    }

    /**
     * Returns discount_amount
     *
     * @return float
     */
    public function getDiscountAmount()
    {
        return $this->getData(OrderInterface::DISCOUNT_AMOUNT);
    }

    /**
     * Returns discount_canceled
     *
     * @return float
     */
    public function getDiscountCanceled()
    {
        return $this->getData(OrderInterface::DISCOUNT_CANCELED);
    }

    /**
     * Returns discount_description
     *
     * @return string
     */
    public function getDiscountDescription()
    {
        return $this->getData(OrderInterface::DISCOUNT_DESCRIPTION);
    }

    /**
     * Returns discount_invoiced
     *
     * @return float
     */
    public function getDiscountInvoiced()
    {
        return $this->getData(OrderInterface::DISCOUNT_INVOICED);
    }

    /**
     * Returns discount_refunded
     *
     * @return float
     */
    public function getDiscountRefunded()
    {
        return $this->getData(OrderInterface::DISCOUNT_REFUNDED);
    }

    /**
     * Returns edit_increment
     *
     * @return int
     */
    public function getEditIncrement()
    {
        return $this->getData(OrderInterface::EDIT_INCREMENT);
    }

    /**
     * Returns email_sent
     *
     * @return int
     */
    public function getEmailSent()
    {
        return $this->getData(OrderInterface::EMAIL_SENT);
    }

    /**
     * Returns ext_customer_id
     *
     * @return string
     */
    public function getExtCustomerId()
    {
        return $this->getData(OrderInterface::EXT_CUSTOMER_ID);
    }

    /**
     * Returns ext_order_id
     *
     * @return string
     */
    public function getExtOrderId()
    {
        return $this->getData(OrderInterface::EXT_ORDER_ID);
    }

    /**
     * Returns forced_shipment_with_invoice
     *
     * @return int
     */
    public function getForcedShipmentWithInvoice()
    {
        return $this->getData(OrderInterface::FORCED_SHIPMENT_WITH_INVOICE);
    }

    /**
     * Returns global_currency_code
     *
     * @return string
     */
    public function getGlobalCurrencyCode()
    {
        return $this->getData(OrderInterface::GLOBAL_CURRENCY_CODE);
    }

    /**
     * Returns grand_total
     *
     * @return float
     */
    public function getGrandTotal()
    {
        return $this->getData(OrderInterface::GRAND_TOTAL);
    }

    /**
     * Returns discount_tax_compensation_amount
     *
     * @return float
     */
    public function getDiscountTaxCompensationAmount()
    {
        return $this->getData(OrderInterface::DISCOUNT_TAX_COMPENSATION_AMOUNT);
    }

    /**
     * Returns discount_tax_compensation_invoiced
     *
     * @return float
     */
    public function getDiscountTaxCompensationInvoiced()
    {
        return $this->getData(OrderInterface::DISCOUNT_TAX_COMPENSATION_INVOICED);
    }

    /**
     * Returns discount_tax_compensation_refunded
     *
     * @return float
     */
    public function getDiscountTaxCompensationRefunded()
    {
        return $this->getData(OrderInterface::DISCOUNT_TAX_COMPENSATION_REFUNDED);
    }

    /**
     * Returns hold_before_state
     *
     * @return string
     */
    public function getHoldBeforeState()
    {
        return $this->getData(OrderInterface::HOLD_BEFORE_STATE);
    }

    /**
     * Returns hold_before_status
     *
     * @return string
     */
    public function getHoldBeforeStatus()
    {
        return $this->getData(OrderInterface::HOLD_BEFORE_STATUS);
    }

    /**
     * Returns is_virtual
     *
     * @return int
     */
    public function getIsVirtual()
    {
        return $this->getData(OrderInterface::IS_VIRTUAL);
    }

    /**
     * Returns order_currency_code
     *
     * @return string
     */
    public function getOrderCurrencyCode()
    {
        return $this->getData(OrderInterface::ORDER_CURRENCY_CODE);
    }

    /**
     * Returns original_increment_id
     *
     * @return string
     */
    public function getOriginalIncrementId()
    {
        return $this->getData(OrderInterface::ORIGINAL_INCREMENT_ID);
    }

    /**
     * Returns payment_authorization_amount
     *
     * @return float
     */
    public function getPaymentAuthorizationAmount()
    {
        return $this->getData(OrderInterface::PAYMENT_AUTHORIZATION_AMOUNT);
    }

    /**
     * Returns payment_auth_expiration
     *
     * @return int
     */
    public function getPaymentAuthExpiration()
    {
        return $this->getData(OrderInterface::PAYMENT_AUTH_EXPIRATION);
    }

    /**
     * Returns protect_code
     *
     * @return string
     */
    public function getProtectCode()
    {
        return $this->getData(OrderInterface::PROTECT_CODE);
    }

    /**
     * Returns quote_address_id
     *
     * @return int
     */
    public function getQuoteAddressId()
    {
        return $this->getData(OrderInterface::QUOTE_ADDRESS_ID);
    }

    /**
     * Returns quote_id
     *
     * @return int
     */
    public function getQuoteId()
    {
        return $this->getData(OrderInterface::QUOTE_ID);
    }

    /**
     * Returns relation_child_id
     *
     * @return string
     */
    public function getRelationChildId()
    {
        return $this->getData(OrderInterface::RELATION_CHILD_ID);
    }

    /**
     * Returns relation_child_real_id
     *
     * @return string
     */
    public function getRelationChildRealId()
    {
        return $this->getData(OrderInterface::RELATION_CHILD_REAL_ID);
    }

    /**
     * Returns relation_parent_id
     *
     * @return string
     */
    public function getRelationParentId()
    {
        return $this->getData(OrderInterface::RELATION_PARENT_ID);
    }

    /**
     * Returns relation_parent_real_id
     *
     * @return string
     */
    public function getRelationParentRealId()
    {
        return $this->getData(OrderInterface::RELATION_PARENT_REAL_ID);
    }

    /**
     * Returns remote_ip
     *
     * @return string
     */
    public function getRemoteIp()
    {
        return $this->getData(OrderInterface::REMOTE_IP);
    }

    /**
     * Returns shipping_address_id
     *
     * @return int
     */
    public function getShippingAddressId()
    {
        return $this->getData(OrderInterface::SHIPPING_ADDRESS_ID);
    }

    /**
     * Returns shipping_amount
     *
     * @return float
     */
    public function getShippingAmount()
    {
        return $this->getData(OrderInterface::SHIPPING_AMOUNT);
    }

    /**
     * Returns shipping_canceled
     *
     * @return float
     */
    public function getShippingCanceled()
    {
        return $this->getData(OrderInterface::SHIPPING_CANCELED);
    }

    /**
     * Returns shipping_description
     *
     * @return string
     */
    public function getShippingDescription()
    {
        return $this->getData(OrderInterface::SHIPPING_DESCRIPTION);
    }

    /**
     * Returns shipping_discount_amount
     *
     * @return float
     */
    public function getShippingDiscountAmount()
    {
        return $this->getData(OrderInterface::SHIPPING_DISCOUNT_AMOUNT);
    }

    /**
     * Returns shipping_discount_tax_compensation_amount
     *
     * @return float
     */
    public function getShippingDiscountTaxCompensationAmount()
    {
        return $this->getData(OrderInterface::SHIPPING_DISCOUNT_TAX_COMPENSATION_AMOUNT);
    }

    /**
     * Returns shipping_incl_tax
     *
     * @return float
     */
    public function getShippingInclTax()
    {
        return $this->getData(OrderInterface::SHIPPING_INCL_TAX);
    }

    /**
     * Returns shipping_invoiced
     *
     * @return float
     */
    public function getShippingInvoiced()
    {
        return $this->getData(OrderInterface::SHIPPING_INVOICED);
    }

    /**
     * Returns shipping_refunded
     *
     * @return float
     */
    public function getShippingRefunded()
    {
        return $this->getData(OrderInterface::SHIPPING_REFUNDED);
    }

    /**
     * Returns shipping_tax_amount
     *
     * @return float
     */
    public function getShippingTaxAmount()
    {
        return $this->getData(OrderInterface::SHIPPING_TAX_AMOUNT);
    }

    /**
     * Returns shipping_tax_refunded
     *
     * @return float
     */
    public function getShippingTaxRefunded()
    {
        return $this->getData(OrderInterface::SHIPPING_TAX_REFUNDED);
    }

    /**
     * Returns state
     *
     * @return string
     */
    public function getState()
    {
        return $this->getData(OrderInterface::STATE);
    }

    /**
     * Returns status
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->getData(OrderInterface::STATUS);
    }

    /**
     * Returns store_currency_code
     *
     * @return string
     */
    public function getStoreCurrencyCode()
    {
        return $this->getData(OrderInterface::STORE_CURRENCY_CODE);
    }

    /**
     * Returns store_id
     *
     * @return int
     */
    public function getStoreId()
    {
        return $this->getData(OrderInterface::STORE_ID);
    }

    /**
     * Returns store_name
     *
     * @return string
     */
    public function getStoreName()
    {
        return $this->getData(OrderInterface::STORE_NAME);
    }

    /**
     * Returns store_to_base_rate
     *
     * @return float
     */
    public function getStoreToBaseRate()
    {
        return $this->getData(OrderInterface::STORE_TO_BASE_RATE);
    }

    /**
     * Returns store_to_order_rate
     *
     * @return float
     */
    public function getStoreToOrderRate()
    {
        return $this->getData(OrderInterface::STORE_TO_ORDER_RATE);
    }

    /**
     * Returns subtotal
     *
     * @return float
     */
    public function getSubtotal()
    {
        return $this->getData(OrderInterface::SUBTOTAL);
    }

    /**
     * Returns subtotal_canceled
     *
     * @return float
     */
    public function getSubtotalCanceled()
    {
        return $this->getData(OrderInterface::SUBTOTAL_CANCELED);
    }

    /**
     * Returns subtotal_incl_tax
     *
     * @return float
     */
    public function getSubtotalInclTax()
    {
        return $this->getData(OrderInterface::SUBTOTAL_INCL_TAX);
    }

    /**
     * Returns subtotal_invoiced
     *
     * @return float
     */
    public function getSubtotalInvoiced()
    {
        return $this->getData(OrderInterface::SUBTOTAL_INVOICED);
    }

    /**
     * Returns subtotal_refunded
     *
     * @return float
     */
    public function getSubtotalRefunded()
    {
        return $this->getData(OrderInterface::SUBTOTAL_REFUNDED);
    }

    /**
     * Returns tax_amount
     *
     * @return float
     */
    public function getTaxAmount()
    {
        return $this->getData(OrderInterface::TAX_AMOUNT);
    }

    /**
     * Returns tax_canceled
     *
     * @return float
     */
    public function getTaxCanceled()
    {
        return $this->getData(OrderInterface::TAX_CANCELED);
    }

    /**
     * Returns tax_invoiced
     *
     * @return float
     */
    public function getTaxInvoiced()
    {
        return $this->getData(OrderInterface::TAX_INVOICED);
    }

    /**
     * Returns tax_refunded
     *
     * @return float
     */
    public function getTaxRefunded()
    {
        return $this->getData(OrderInterface::TAX_REFUNDED);
    }

    /**
     * Returns total_canceled
     *
     * @return float
     */
    public function getTotalCanceled()
    {
        return $this->getData(OrderInterface::TOTAL_CANCELED);
    }

    /**
     * Returns total_invoiced
     *
     * @return float
     */
    public function getTotalInvoiced()
    {
        return $this->getData(OrderInterface::TOTAL_INVOICED);
    }

    /**
     * Returns total_item_count
     *
     * @return int
     */
    public function getTotalItemCount()
    {
        return $this->getData(OrderInterface::TOTAL_ITEM_COUNT);
    }

    /**
     * Returns total_offline_refunded
     *
     * @return float
     */
    public function getTotalOfflineRefunded()
    {
        return $this->getData(OrderInterface::TOTAL_OFFLINE_REFUNDED);
    }

    /**
     * Returns total_online_refunded
     *
     * @return float
     */
    public function getTotalOnlineRefunded()
    {
        return $this->getData(OrderInterface::TOTAL_ONLINE_REFUNDED);
    }

    /**
     * Returns total_paid
     *
     * @return float
     */
    public function getTotalPaid()
    {
        return $this->getData(OrderInterface::TOTAL_PAID);
    }

    /**
     * Returns total_qty_ordered
     *
     * @return float
     */
    public function getTotalQtyOrdered()
    {
        return $this->getData(OrderInterface::TOTAL_QTY_ORDERED);
    }

    /**
     * Returns total_refunded
     *
     * @return float
     */
    public function getTotalRefunded()
    {
        return $this->getData(OrderInterface::TOTAL_REFUNDED);
    }

    /**
     * Returns updated_at
     *
     * @return string
     */
    public function getUpdatedAt()
    {
        return $this->getData(OrderInterface::UPDATED_AT);
    }

    /**
     * Returns weight
     *
     * @return float
     */
    public function getWeight()
    {
        return $this->getData(OrderInterface::WEIGHT);
    }

    /**
     * Returns x_forwarded_for
     *
     * @return string
     */
    public function getXForwardedFor()
    {
        return $this->getData(OrderInterface::X_FORWARDED_FOR);
    }

    /**
     * {@inheritdoc}
     */
    public function setStatusHistories(array $statusHistories = null)
    {
        return $this->setData(OrderInterface::STATUS_HISTORIES, $statusHistories);
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus($status)
    {
        return $this->setData(OrderInterface::STATUS, $status);
    }

    /**
     * {@inheritdoc}
     */
    public function setCouponCode($code)
    {
        return $this->setData(OrderInterface::COUPON_CODE, $code);
    }

    /**
     * {@inheritdoc}
     */
    public function setProtectCode($code)
    {
        return $this->setData(OrderInterface::PROTECT_CODE, $code);
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingDescription($description)
    {
        return $this->setData(OrderInterface::SHIPPING_DESCRIPTION, $description);
    }

    /**
     * {@inheritdoc}
     */
    public function setIsVirtual($isVirtual)
    {
        return $this->setData(OrderInterface::IS_VIRTUAL, $isVirtual);
    }

    /**
     * {@inheritdoc}
     */
    public function setStoreId($id)
    {
        return $this->setData(OrderInterface::STORE_ID, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerId($id)
    {
        return $this->setData(OrderInterface::CUSTOMER_ID, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseDiscountAmount($amount)
    {
        return $this->setData(OrderInterface::BASE_DISCOUNT_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseDiscountCanceled($baseDiscountCanceled)
    {
        return $this->setData(OrderInterface::BASE_DISCOUNT_CANCELED, $baseDiscountCanceled);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseDiscountInvoiced($baseDiscountInvoiced)
    {
        return $this->setData(OrderInterface::BASE_DISCOUNT_INVOICED, $baseDiscountInvoiced);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseDiscountRefunded($baseDiscountRefunded)
    {
        return $this->setData(OrderInterface::BASE_DISCOUNT_REFUNDED, $baseDiscountRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseGrandTotal($amount)
    {
        return $this->setData(OrderInterface::BASE_GRAND_TOTAL, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseShippingAmount($amount)
    {
        return $this->setData(OrderInterface::BASE_SHIPPING_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseShippingCanceled($baseShippingCanceled)
    {
        return $this->setData(OrderInterface::BASE_SHIPPING_CANCELED, $baseShippingCanceled);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseShippingInvoiced($baseShippingInvoiced)
    {
        return $this->setData(OrderInterface::BASE_SHIPPING_INVOICED, $baseShippingInvoiced);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseShippingRefunded($baseShippingRefunded)
    {
        return $this->setData(OrderInterface::BASE_SHIPPING_REFUNDED, $baseShippingRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseShippingTaxAmount($amount)
    {
        return $this->setData(OrderInterface::BASE_SHIPPING_TAX_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseShippingTaxRefunded($baseShippingTaxRefunded)
    {
        return $this->setData(OrderInterface::BASE_SHIPPING_TAX_REFUNDED, $baseShippingTaxRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseSubtotal($amount)
    {
        return $this->setData(OrderInterface::BASE_SUBTOTAL, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseSubtotalCanceled($baseSubtotalCanceled)
    {
        return $this->setData(OrderInterface::BASE_SUBTOTAL_CANCELED, $baseSubtotalCanceled);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseSubtotalInvoiced($baseSubtotalInvoiced)
    {
        return $this->setData(OrderInterface::BASE_SUBTOTAL_INVOICED, $baseSubtotalInvoiced);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseSubtotalRefunded($baseSubtotalRefunded)
    {
        return $this->setData(OrderInterface::BASE_SUBTOTAL_REFUNDED, $baseSubtotalRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseTaxAmount($amount)
    {
        return $this->setData(OrderInterface::BASE_TAX_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseTaxCanceled($baseTaxCanceled)
    {
        return $this->setData(OrderInterface::BASE_TAX_CANCELED, $baseTaxCanceled);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseTaxInvoiced($baseTaxInvoiced)
    {
        return $this->setData(OrderInterface::BASE_TAX_INVOICED, $baseTaxInvoiced);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseTaxRefunded($baseTaxRefunded)
    {
        return $this->setData(OrderInterface::BASE_TAX_REFUNDED, $baseTaxRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseToGlobalRate($rate)
    {
        return $this->setData(OrderInterface::BASE_TO_GLOBAL_RATE, $rate);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseToOrderRate($rate)
    {
        return $this->setData(OrderInterface::BASE_TO_ORDER_RATE, $rate);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseTotalCanceled($baseTotalCanceled)
    {
        return $this->setData(OrderInterface::BASE_TOTAL_CANCELED, $baseTotalCanceled);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseTotalInvoiced($baseTotalInvoiced)
    {
        return $this->setData(OrderInterface::BASE_TOTAL_INVOICED, $baseTotalInvoiced);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseTotalInvoicedCost($baseTotalInvoicedCost)
    {
        return $this->setData(OrderInterface::BASE_TOTAL_INVOICED_COST, $baseTotalInvoicedCost);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseTotalOfflineRefunded($baseTotalOfflineRefunded)
    {
        return $this->setData(OrderInterface::BASE_TOTAL_OFFLINE_REFUNDED, $baseTotalOfflineRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseTotalOnlineRefunded($baseTotalOnlineRefunded)
    {
        return $this->setData(OrderInterface::BASE_TOTAL_ONLINE_REFUNDED, $baseTotalOnlineRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseTotalPaid($baseTotalPaid)
    {
        return $this->setData(OrderInterface::BASE_TOTAL_PAID, $baseTotalPaid);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseTotalQtyOrdered($baseTotalQtyOrdered)
    {
        return $this->setData(OrderInterface::BASE_TOTAL_QTY_ORDERED, $baseTotalQtyOrdered);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseTotalRefunded($baseTotalRefunded)
    {
        return $this->setData(OrderInterface::BASE_TOTAL_REFUNDED, $baseTotalRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setDiscountAmount($amount)
    {
        return $this->setData(OrderInterface::DISCOUNT_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setDiscountCanceled($discountCanceled)
    {
        return $this->setData(OrderInterface::DISCOUNT_CANCELED, $discountCanceled);
    }

    /**
     * {@inheritdoc}
     */
    public function setDiscountInvoiced($discountInvoiced)
    {
        return $this->setData(OrderInterface::DISCOUNT_INVOICED, $discountInvoiced);
    }

    /**
     * {@inheritdoc}
     */
    public function setDiscountRefunded($discountRefunded)
    {
        return $this->setData(OrderInterface::DISCOUNT_REFUNDED, $discountRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setGrandTotal($amount)
    {
        return $this->setData(OrderInterface::GRAND_TOTAL, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingAmount($amount)
    {
        return $this->setData(OrderInterface::SHIPPING_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingCanceled($shippingCanceled)
    {
        return $this->setData(OrderInterface::SHIPPING_CANCELED, $shippingCanceled);
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingInvoiced($shippingInvoiced)
    {
        return $this->setData(OrderInterface::SHIPPING_INVOICED, $shippingInvoiced);
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingRefunded($shippingRefunded)
    {
        return $this->setData(OrderInterface::SHIPPING_REFUNDED, $shippingRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingTaxAmount($amount)
    {
        return $this->setData(OrderInterface::SHIPPING_TAX_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingTaxRefunded($shippingTaxRefunded)
    {
        return $this->setData(OrderInterface::SHIPPING_TAX_REFUNDED, $shippingTaxRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setStoreToBaseRate($rate)
    {
        return $this->setData(OrderInterface::STORE_TO_BASE_RATE, $rate);
    }

    /**
     * {@inheritdoc}
     */
    public function setStoreToOrderRate($rate)
    {
        return $this->setData(OrderInterface::STORE_TO_ORDER_RATE, $rate);
    }

    /**
     * {@inheritdoc}
     */
    public function setSubtotal($amount)
    {
        return $this->setData(OrderInterface::SUBTOTAL, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setSubtotalCanceled($subtotalCanceled)
    {
        return $this->setData(OrderInterface::SUBTOTAL_CANCELED, $subtotalCanceled);
    }

    /**
     * {@inheritdoc}
     */
    public function setSubtotalInvoiced($subtotalInvoiced)
    {
        return $this->setData(OrderInterface::SUBTOTAL_INVOICED, $subtotalInvoiced);
    }

    /**
     * {@inheritdoc}
     */
    public function setSubtotalRefunded($subtotalRefunded)
    {
        return $this->setData(OrderInterface::SUBTOTAL_REFUNDED, $subtotalRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setTaxAmount($amount)
    {
        return $this->setData(OrderInterface::TAX_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setTaxCanceled($taxCanceled)
    {
        return $this->setData(OrderInterface::TAX_CANCELED, $taxCanceled);
    }

    /**
     * {@inheritdoc}
     */
    public function setTaxInvoiced($taxInvoiced)
    {
        return $this->setData(OrderInterface::TAX_INVOICED, $taxInvoiced);
    }

    /**
     * {@inheritdoc}
     */
    public function setTaxRefunded($taxRefunded)
    {
        return $this->setData(OrderInterface::TAX_REFUNDED, $taxRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalCanceled($totalCanceled)
    {
        return $this->setData(OrderInterface::TOTAL_CANCELED, $totalCanceled);
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalInvoiced($totalInvoiced)
    {
        return $this->setData(OrderInterface::TOTAL_INVOICED, $totalInvoiced);
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalOfflineRefunded($totalOfflineRefunded)
    {
        return $this->setData(OrderInterface::TOTAL_OFFLINE_REFUNDED, $totalOfflineRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalOnlineRefunded($totalOnlineRefunded)
    {
        return $this->setData(OrderInterface::TOTAL_ONLINE_REFUNDED, $totalOnlineRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalPaid($totalPaid)
    {
        return $this->setData(OrderInterface::TOTAL_PAID, $totalPaid);
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalQtyOrdered($totalQtyOrdered)
    {
        return $this->setData(OrderInterface::TOTAL_QTY_ORDERED, $totalQtyOrdered);
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalRefunded($totalRefunded)
    {
        return $this->setData(OrderInterface::TOTAL_REFUNDED, $totalRefunded);
    }

    /**
     * {@inheritdoc}
     */
    public function setCanShipPartially($flag)
    {
        return $this->setData(OrderInterface::CAN_SHIP_PARTIALLY, $flag);
    }

    /**
     * {@inheritdoc}
     */
    public function setCanShipPartiallyItem($flag)
    {
        return $this->setData(OrderInterface::CAN_SHIP_PARTIALLY_ITEM, $flag);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerIsGuest($customerIsGuest)
    {
        return $this->setData(OrderInterface::CUSTOMER_IS_GUEST, $customerIsGuest);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerNoteNotify($customerNoteNotify)
    {
        return $this->setData(OrderInterface::CUSTOMER_NOTE_NOTIFY, $customerNoteNotify);
    }

    /**
     * {@inheritdoc}
     */
    public function setBillingAddressId($id)
    {
        return $this->setData(OrderInterface::BILLING_ADDRESS_ID, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerGroupId($id)
    {
        return $this->setData(OrderInterface::CUSTOMER_GROUP_ID, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function setEditIncrement($editIncrement)
    {
        return $this->setData(OrderInterface::EDIT_INCREMENT, $editIncrement);
    }

    /**
     * {@inheritdoc}
     */
    public function setEmailSent($emailSent)
    {
        return $this->setData(OrderInterface::EMAIL_SENT, $emailSent);
    }

    /**
     * {@inheritdoc}
     */
    public function setForcedShipmentWithInvoice($forcedShipmentWithInvoice)
    {
        return $this->setData(OrderInterface::FORCED_SHIPMENT_WITH_INVOICE, $forcedShipmentWithInvoice);
    }

    /**
     * {@inheritdoc}
     */
    public function setPaymentAuthExpiration($paymentAuthExpiration)
    {
        return $this->setData(OrderInterface::PAYMENT_AUTH_EXPIRATION, $paymentAuthExpiration);
    }

    /**
     * {@inheritdoc}
     */
    public function setQuoteAddressId($id)
    {
        return $this->setData(OrderInterface::QUOTE_ADDRESS_ID, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function setQuoteId($id)
    {
        return $this->setData(OrderInterface::QUOTE_ID, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingAddressId($id)
    {
        return $this->setData(OrderInterface::SHIPPING_ADDRESS_ID, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function setAdjustmentNegative($adjustmentNegative)
    {
        return $this->setData(OrderInterface::ADJUSTMENT_NEGATIVE, $adjustmentNegative);
    }

    /**
     * {@inheritdoc}
     */
    public function setAdjustmentPositive($adjustmentPositive)
    {
        return $this->setData(OrderInterface::ADJUSTMENT_POSITIVE, $adjustmentPositive);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseAdjustmentNegative($baseAdjustmentNegative)
    {
        return $this->setData(OrderInterface::BASE_ADJUSTMENT_NEGATIVE, $baseAdjustmentNegative);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseAdjustmentPositive($baseAdjustmentPositive)
    {
        return $this->setData(OrderInterface::BASE_ADJUSTMENT_POSITIVE, $baseAdjustmentPositive);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseShippingDiscountAmount($amount)
    {
        return $this->setData(OrderInterface::BASE_SHIPPING_DISCOUNT_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseSubtotalInclTax($amount)
    {
        return $this->setData(OrderInterface::BASE_SUBTOTAL_INCL_TAX, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseTotalDue($baseTotalDue)
    {
        return $this->setData(OrderInterface::BASE_TOTAL_DUE, $baseTotalDue);
    }

    /**
     * {@inheritdoc}
     */
    public function setPaymentAuthorizationAmount($amount)
    {
        return $this->setData(OrderInterface::PAYMENT_AUTHORIZATION_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingDiscountAmount($amount)
    {
        return $this->setData(OrderInterface::SHIPPING_DISCOUNT_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setSubtotalInclTax($amount)
    {
        return $this->setData(OrderInterface::SUBTOTAL_INCL_TAX, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalDue($totalDue)
    {
        return $this->setData(OrderInterface::TOTAL_DUE, $totalDue);
    }

    /**
     * {@inheritdoc}
     */
    public function setWeight($weight)
    {
        return $this->setData(OrderInterface::WEIGHT, $weight);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerDob($customerDob)
    {
        return $this->setData(OrderInterface::CUSTOMER_DOB, $customerDob);
    }

    /**
     * {@inheritdoc}
     */
    public function setIncrementId($id)
    {
        return $this->setData(OrderInterface::INCREMENT_ID, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function setAppliedRuleIds($appliedRuleIds)
    {
        return $this->setData(OrderInterface::APPLIED_RULE_IDS, $appliedRuleIds);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseCurrencyCode($code)
    {
        return $this->setData(OrderInterface::BASE_CURRENCY_CODE, $code);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerEmail($customerEmail)
    {
        return $this->setData(OrderInterface::CUSTOMER_EMAIL, $customerEmail);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerFirstname($customerFirstname)
    {
        return $this->setData(OrderInterface::CUSTOMER_FIRSTNAME, $customerFirstname);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerLastname($customerLastname)
    {
        return $this->setData(OrderInterface::CUSTOMER_LASTNAME, $customerLastname);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerMiddlename($customerMiddlename)
    {
        return $this->setData(OrderInterface::CUSTOMER_MIDDLENAME, $customerMiddlename);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerPrefix($customerPrefix)
    {
        return $this->setData(OrderInterface::CUSTOMER_PREFIX, $customerPrefix);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerSuffix($customerSuffix)
    {
        return $this->setData(OrderInterface::CUSTOMER_SUFFIX, $customerSuffix);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerTaxvat($customerTaxvat)
    {
        return $this->setData(OrderInterface::CUSTOMER_TAXVAT, $customerTaxvat);
    }

    /**
     * {@inheritdoc}
     */
    public function setDiscountDescription($description)
    {
        return $this->setData(OrderInterface::DISCOUNT_DESCRIPTION, $description);
    }

    /**
     * {@inheritdoc}
     */
    public function setExtCustomerId($id)
    {
        return $this->setData(OrderInterface::EXT_CUSTOMER_ID, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function setExtOrderId($id)
    {
        return $this->setData(OrderInterface::EXT_ORDER_ID, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function setGlobalCurrencyCode($code)
    {
        return $this->setData(OrderInterface::GLOBAL_CURRENCY_CODE, $code);
    }

    /**
     * {@inheritdoc}
     */
    public function setHoldBeforeState($holdBeforeState)
    {
        return $this->setData(OrderInterface::HOLD_BEFORE_STATE, $holdBeforeState);
    }

    /**
     * {@inheritdoc}
     */
    public function setHoldBeforeStatus($holdBeforeStatus)
    {
        return $this->setData(OrderInterface::HOLD_BEFORE_STATUS, $holdBeforeStatus);
    }

    /**
     * {@inheritdoc}
     */
    public function setOrderCurrencyCode($code)
    {
        return $this->setData(OrderInterface::ORDER_CURRENCY_CODE, $code);
    }

    /**
     * {@inheritdoc}
     */
    public function setOriginalIncrementId($id)
    {
        return $this->setData(OrderInterface::ORIGINAL_INCREMENT_ID, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function setRelationChildId($id)
    {
        return $this->setData(OrderInterface::RELATION_CHILD_ID, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function setRelationChildRealId($realId)
    {
        return $this->setData(OrderInterface::RELATION_CHILD_REAL_ID, $realId);
    }

    /**
     * {@inheritdoc}
     */
    public function setRelationParentId($id)
    {
        return $this->setData(OrderInterface::RELATION_PARENT_ID, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function setRelationParentRealId($realId)
    {
        return $this->setData(OrderInterface::RELATION_PARENT_REAL_ID, $realId);
    }

    /**
     * {@inheritdoc}
     */
    public function setRemoteIp($remoteIp)
    {
        return $this->setData(OrderInterface::REMOTE_IP, $remoteIp);
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingMethod($shippingMethod)
    {
        return $this->setData(OrderInterface::SHIPPING_METHOD, $shippingMethod);
    }

    /**
     * {@inheritdoc}
     */
    public function setStoreCurrencyCode($code)
    {
        return $this->setData(OrderInterface::STORE_CURRENCY_CODE, $code);
    }

    /**
     * {@inheritdoc}
     */
    public function setStoreName($storeName)
    {
        return $this->setData(OrderInterface::STORE_NAME, $storeName);
    }

    /**
     * {@inheritdoc}
     */
    public function setXForwardedFor($xForwardedFor)
    {
        return $this->setData(OrderInterface::X_FORWARDED_FOR, $xForwardedFor);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerNote($customerNote)
    {
        return $this->setData(OrderInterface::CUSTOMER_NOTE, $customerNote);
    }

    /**
     * {@inheritdoc}
     */
    public function setUpdatedAt($timestamp)
    {
        return $this->setData(OrderInterface::UPDATED_AT, $timestamp);
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalItemCount($totalItemCount)
    {
        return $this->setData(OrderInterface::TOTAL_ITEM_COUNT, $totalItemCount);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerGender($customerGender)
    {
        return $this->setData(OrderInterface::CUSTOMER_GENDER, $customerGender);
    }

    /**
     * {@inheritdoc}
     */
    public function setDiscountTaxCompensationAmount($amount)
    {
        return $this->setData(OrderInterface::DISCOUNT_TAX_COMPENSATION_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseDiscountTaxCompensationAmount($amount)
    {
        return $this->setData(OrderInterface::BASE_DISCOUNT_TAX_COMPENSATION_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingDiscountTaxCompensationAmount($amount)
    {
        return $this->setData(OrderInterface::SHIPPING_DISCOUNT_TAX_COMPENSATION_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseShippingDiscountTaxCompensationAmnt($amnt)
    {
        return $this->setData(OrderInterface::BASE_SHIPPING_DISCOUNT_TAX_COMPENSATION_AMNT, $amnt);
    }

    /**
     * {@inheritdoc}
     */
    public function setDiscountTaxCompensationInvoiced($discountTaxCompensationInvoiced)
    {
        return $this->setData(OrderInterface::DISCOUNT_TAX_COMPENSATION_INVOICED, $discountTaxCompensationInvoiced);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseDiscountTaxCompensationInvoiced($baseDiscountTaxCompensationInvoiced)
    {
        return $this->setData(
            OrderInterface::BASE_DISCOUNT_TAX_COMPENSATION_INVOICED,
            $baseDiscountTaxCompensationInvoiced
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setDiscountTaxCompensationRefunded($discountTaxCompensationRefunded)
    {
        return $this->setData(
            OrderInterface::DISCOUNT_TAX_COMPENSATION_REFUNDED,
            $discountTaxCompensationRefunded
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseDiscountTaxCompensationRefunded($baseDiscountTaxCompensationRefunded)
    {
        return $this->setData(
            OrderInterface::BASE_DISCOUNT_TAX_COMPENSATION_REFUNDED,
            $baseDiscountTaxCompensationRefunded
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingInclTax($amount)
    {
        return $this->setData(OrderInterface::SHIPPING_INCL_TAX, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseShippingInclTax($amount)
    {
        return $this->setData(OrderInterface::BASE_SHIPPING_INCL_TAX, $amount);
    }
    //@codeCoverageIgnoreEnd
}
