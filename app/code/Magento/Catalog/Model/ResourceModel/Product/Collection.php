<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace Magento\Catalog\Model\ResourceModel\Product;

use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Model\Store;

/**
 * Product collection
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *
 * @method \Magento\Eav\Model\ResourceModel\Attribute\DefaultEntityAttributes\ProviderInterface getResource()
 */
class Collection extends \Magento\Catalog\Model\ResourceModel\Collection\AbstractCollection
{
    /**
     * Alias for index table
     */
    const INDEX_TABLE_ALIAS = 'price_index';

    /**
     * Alias for main table
     */
    const MAIN_TABLE_ALIAS = 'e';

    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * Catalog Product Flat is enabled cache per store
     *
     * @var array
     */
    protected $_flatEnabled = [];

    /**
     * Product websites table name
     *
     * @var string
     */
    protected $_productWebsiteTable;

    /**
     * Product categories table name
     *
     * @var string
     */
    protected $_productCategoryTable;

    /**
     * Is add URL rewrites to collection flag
     *
     * @var bool
     */
    protected $_addUrlRewrite = false;

    /**
     * Add URL rewrite for category
     *
     * @var int
     */
    protected $_urlRewriteCategory = '';

    /**
     * Is add final price to product collection flag
     *
     * @var bool
     */
    protected $_addFinalPrice = false;

    /**
     * Cache for all ids
     *
     * @var array
     */
    protected $_allIdsCache = null;

    /**
     * Is add tax percents to product collection flag
     *
     * @var bool
     */
    protected $_addTaxPercents = false;

    /**
     * Product limitation filters
     * Allowed filters
     *  store_id                int;
     *  category_id             int;
     *  category_is_anchor      int;
     *  visibility              array|int;
     *  website_ids             array|int;
     *  store_table             string;
     *  use_price_index         bool;   join price index table flag
     *  customer_group_id       int;    required for price; customer group limitation for price
     *  website_id              int;    required for price; website limitation for price
     *
     * @var array
     */
    protected $_productLimitationFilters = [];

    /**
     * Category product count select
     *
     * @var \Magento\Framework\DB\Select
     */
    protected $_productCountSelect = null;

    /**
     * @var bool
     */
    protected $_isWebsiteFilter = false;

    /**
     * Additional field filters, applied in _productLimitationJoinPrice()
     *
     * @var array
     */
    protected $_priceDataFieldFilters = [];

    /**
     * Price expression sql
     *
     * @var string|null
     */
    protected $_priceExpression;

    /**
     * Additional price expression sql part
     *
     * @var string|null
     */
    protected $_additionalPriceExpression;

    /**
     * Max prise (statistics data)
     *
     * @var float
     */
    protected $_maxPrice;

    /**
     * Min prise (statistics data)
     *
     * @var float
     */
    protected $_minPrice;

    /**
     * Prise standard deviation (statistics data)
     *
     * @var float
     */
    protected $_priceStandardDeviation;

    /**
     * Prises count (statistics data)
     *
     * @var int
     */
    protected $_pricesCount = null;

    /**
     * Cloned Select after dispatching 'catalog_prepare_price_select' event
     *
     * @var \Magento\Framework\DB\Select
     */
    protected $_catalogPreparePriceSelect = null;

    /**
     * Catalog product flat
     *
     * @var \Magento\Catalog\Model\Indexer\Product\Flat\State
     */
    protected $_catalogProductFlatState = null;

    /**
     * Catalog data
     *
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager = null;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * Customer session
     *
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $_localeDate;

    /**
     * Catalog url
     *
     * @var \Magento\Catalog\Model\ResourceModel\Url
     */
    protected $_catalogUrl;

    /**
     * Product option factory
     *
     * @var \Magento\Catalog\Model\Product\OptionFactory
     */
    protected $_productOptionFactory;

    /**
     * Catalog resource helper
     *
     * @var \Magento\Catalog\Model\ResourceModel\Helper
     */
    protected $_resourceHelper;

    /**
     * @var \Magento\Framework\Stdlib\DateTime
     */
    protected $dateTime;

    /**
     * @var GroupManagementInterface
     */
    protected $_groupManagement;

    /**
     * Need to add websites to result flag
     *
     * @var bool
     */
    protected $needToAddWebsiteNamesToResult;

    /**
     * @param \Magento\Framework\Data\Collection\EntityFactory $entityFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\Eav\Model\EntityFactory $eavEntityFactory
     * @param \Magento\Catalog\Model\ResourceModel\Helper $resourceHelper
     * @param \Magento\Framework\Validator\UniversalFactory $universalFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param \Magento\Catalog\Model\Indexer\Product\Flat\State $catalogProductFlatState
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Catalog\Model\Product\OptionFactory $productOptionFactory
     * @param \Magento\Catalog\Model\ResourceModel\Url $catalogUrl
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Stdlib\DateTime $dateTime
     * @param GroupManagementInterface $groupManagement
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactory $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Eav\Model\EntityFactory $eavEntityFactory,
        \Magento\Catalog\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Framework\Validator\UniversalFactory $universalFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Catalog\Model\Indexer\Product\Flat\State $catalogProductFlatState,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Model\Product\OptionFactory $productOptionFactory,
        \Magento\Catalog\Model\ResourceModel\Url $catalogUrl,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Stdlib\DateTime $dateTime,
        GroupManagementInterface $groupManagement,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null
    ) {
        $this->moduleManager = $moduleManager;
        $this->_catalogProductFlatState = $catalogProductFlatState;
        $this->_scopeConfig = $scopeConfig;
        $this->_productOptionFactory = $productOptionFactory;
        $this->_catalogUrl = $catalogUrl;
        $this->_localeDate = $localeDate;
        $this->_customerSession = $customerSession;
        $this->_resourceHelper = $resourceHelper;
        $this->dateTime = $dateTime;
        $this->_groupManagement = $groupManagement;
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $eavConfig,
            $resource,
            $eavEntityFactory,
            $resourceHelper,
            $universalFactory,
            $storeManager,
            $connection
        );
    }

    /**
     * Get cloned Select after dispatching 'catalog_prepare_price_select' event
     *
     * @return \Magento\Framework\DB\Select
     */
    public function getCatalogPreparedSelect()
    {
        return $this->_catalogPreparePriceSelect;
    }

    /**
     * Prepare additional price expression sql part
     *
     * @param \Magento\Framework\DB\Select $select
     * @return $this
     */
    protected function _preparePriceExpressionParameters($select)
    {
        // prepare response object for event
        $response = new \Magento\Framework\DataObject();
        $response->setAdditionalCalculations([]);
        $tableAliases = array_keys($select->getPart(\Magento\Framework\DB\Select::FROM));
        if (in_array(self::INDEX_TABLE_ALIAS, $tableAliases)) {
            $table = self::INDEX_TABLE_ALIAS;
        } else {
            $table = reset($tableAliases);
        }

        // prepare event arguments
        $eventArgs = [
            'select' => $select,
            'table' => $table,
            'store_id' => $this->getStoreId(),
            'response_object' => $response,
        ];

        $this->_eventManager->dispatch('catalog_prepare_price_select', $eventArgs);

        $additional = join('', $response->getAdditionalCalculations());
        $this->_priceExpression = $table . '.min_price';
        $this->_additionalPriceExpression = $additional;
        $this->_catalogPreparePriceSelect = clone $select;

        return $this;
    }

    /**
     * Get price expression sql part
     *
     * @param \Magento\Framework\DB\Select $select
     * @return string
     */
    public function getPriceExpression($select)
    {
        //@todo: Add caching of price expresion
        $this->_preparePriceExpressionParameters($select);
        return $this->_priceExpression;
    }

    /**
     * Get additional price expression sql part
     *
     * @param \Magento\Framework\DB\Select $select
     * @return string
     */
    public function getAdditionalPriceExpression($select)
    {
        if (null === $this->_additionalPriceExpression) {
            $this->_preparePriceExpressionParameters($select);
        }
        return $this->_additionalPriceExpression;
    }

    /**
     * Get currency rate
     *
     * @return float
     */
    public function getCurrencyRate()
    {
        return $this->_storeManager->getStore($this->getStoreId())->getCurrentCurrencyRate();
    }

    /**
     * Retrieve Catalog Product Flat Helper object
     *
     * @return \Magento\Catalog\Model\Indexer\Product\Flat\State
     */
    public function getFlatState()
    {
        return $this->_catalogProductFlatState;
    }

    /**
     * Retrieve is flat enabled flag
     * Return always false if magento run admin
     *
     * @return bool
     */
    public function isEnabledFlat()
    {
        if (!isset($this->_flatEnabled[$this->getStoreId()])) {
            $this->_flatEnabled[$this->getStoreId()] = $this->getFlatState()->isAvailable();
        }
        return $this->_flatEnabled[$this->getStoreId()];
    }

    /**
     * Initialize resources
     *
     * @return void
     */
    protected function _construct()
    {
        if ($this->isEnabledFlat()) {
            $this->_init('Magento\Catalog\Model\Product', 'Magento\Catalog\Model\ResourceModel\Product\Flat');
        } else {
            $this->_init('Magento\Catalog\Model\Product', 'Magento\Catalog\Model\ResourceModel\Product');
        }
        $this->_initTables();
    }

    /**
     * Standard resource collection initialization
     * Needed for child classes
     *
     * @param string $model
     * @param string $entityModel
     * @return $this
     */
    protected function _init($model, $entityModel)
    {
        if ($this->isEnabledFlat()) {
            $entityModel = 'Magento\Catalog\Model\ResourceModel\Product\Flat';
        }
        return parent::_init($model, $entityModel);
    }

    /**
     * Define product website and category product tables
     *
     * @return void
     */
    protected function _initTables()
    {
        $this->_productWebsiteTable = $this->getResource()->getTable('catalog_product_website');
        $this->_productCategoryTable = $this->getResource()->getTable('catalog_category_product');
    }

    /**
     * Prepare static entity fields
     *
     * @return $this
     */
    protected function _prepareStaticFields()
    {
        if ($this->isEnabledFlat()) {
            return $this;
        }
        return parent::_prepareStaticFields();
    }

    /**
     * Retrieve collection empty item
     * Redeclared for specifying id field name without getting resource model inside model
     *
     * @return \Magento\Framework\DataObject
     */
    public function getNewEmptyItem()
    {
        $object = parent::getNewEmptyItem();
        if ($this->isEnabledFlat()) {
            $object->setIdFieldName($this->getEntity()->getIdFieldName());
        }
        return $object;
    }

    /**
     * Set entity to use for attributes
     *
     * @param \Magento\Eav\Model\Entity\AbstractEntity $entity
     * @return $this
     */
    public function setEntity($entity)
    {
        if ($this->isEnabledFlat() && $entity instanceof \Magento\Framework\Model\ResourceModel\Db\AbstractDb) {
            $this->_entity = $entity;
            return $this;
        }
        return parent::setEntity($entity);
    }

    /**
     * Set Store scope for collection
     *
     * @param mixed $store
     * @return $this
     */
    public function setStore($store)
    {
        parent::setStore($store);
        if ($this->isEnabledFlat()) {
            $this->getEntity()->setStoreId($this->getStoreId());
        }
        return $this;
    }

    /**
     * Initialize collection select
     * Redeclared for remove entity_type_id condition
     * in catalog_product_entity we store just products
     *
     * @return $this
     */
    protected function _initSelect()
    {
        if ($this->isEnabledFlat()) {
            $this->getSelect()->from(
                [self::MAIN_TABLE_ALIAS => $this->getEntity()->getFlatTableName()],
                null
            )->columns(
                ['status' => new \Zend_Db_Expr(ProductStatus::STATUS_ENABLED)]
            );
            $this->addAttributeToSelect($this->getResource()->getDefaultAttributes());
            if ($this->_catalogProductFlatState->getFlatIndexerHelper()->isAddChildData()) {
                $this->getSelect()->where('e.is_child=?', 0);
                $this->addAttributeToSelect(['child_id', 'is_child']);
            }
        } else {
            $this->getSelect()->from([self::MAIN_TABLE_ALIAS => $this->getEntity()->getEntityTable()]);
        }
        return $this;
    }

    /**
     * Load attributes into loaded entities
     *
     * @param bool $printQuery
     * @param bool $logQuery
     * @return $this
     */
    public function _loadAttributes($printQuery = false, $logQuery = false)
    {
        if ($this->isEnabledFlat()) {
            return $this;
        }
        return parent::_loadAttributes($printQuery, $logQuery);
    }

    /**
     * Add attribute to entities in collection
     * If $attribute=='*' select all attributes
     *
     * @param array|string|integer|\Magento\Framework\App\Config\Element $attribute
     * @param bool|string $joinType
     * @return $this
     */
    public function addAttributeToSelect($attribute, $joinType = false)
    {
        if ($this->isEnabledFlat()) {
            if (!is_array($attribute)) {
                $attribute = [$attribute];
            }
            foreach ($attribute as $attributeCode) {
                if ($attributeCode == '*') {
                    foreach ($this->getEntity()->getAllTableColumns() as $column) {
                        $this->getSelect()->columns('e.' . $column);
                        $this->_selectAttributes[$column] = $column;
                        $this->_staticFields[$column] = $column;
                    }
                } else {
                    $columns = $this->getEntity()->getAttributeForSelect($attributeCode);
                    if ($columns) {
                        foreach ($columns as $alias => $column) {
                            $this->getSelect()->columns([$alias => 'e.' . $column]);
                            $this->_selectAttributes[$column] = $column;
                            $this->_staticFields[$column] = $column;
                        }
                    }
                }
            }
            return $this;
        }
        return parent::addAttributeToSelect($attribute, $joinType);
    }

    /**
     * Processing collection items after loading
     * Adding url rewrites, minimal prices, final prices, tax percents
     *
     * @return $this
     */
    protected function _afterLoad()
    {
        if ($this->_addUrlRewrite) {
            $this->_addUrlRewrite();
        }

        $this->_prepareUrlDataObject();

        if (count($this)) {
            $this->_eventManager->dispatch('catalog_product_collection_load_after', ['collection' => $this]);
        }

        return $this;
    }

    /**
     * Prepare Url Data object
     *
     * @return $this
     */
    protected function _prepareUrlDataObject()
    {
        $objects = [];
        /** @var $item \Magento\Catalog\Model\Product */
        foreach ($this->_items as $item) {
            if ($this->getFlag('do_not_use_category_id')) {
                $item->setDoNotUseCategoryId(true);
            }
            if (!$item->isVisibleInSiteVisibility() && $item->getItemStoreId()) {
                $objects[$item->getEntityId()] = $item->getItemStoreId();
            }
        }

        if ($objects && $this->hasFlag('url_data_object')) {
            $objects = $this->_catalogUrl->getRewriteByProductStore($objects);
            foreach ($this->_items as $item) {
                if (isset($objects[$item->getEntityId()])) {
                    $object = new \Magento\Framework\DataObject($objects[$item->getEntityId()]);
                    $item->setUrlDataObject($object);
                }
            }
        }

        return $this;
    }

    /**
     * Add collection filters by identifiers
     *
     * @param mixed $productId
     * @param boolean $exclude
     * @return $this
     */
    public function addIdFilter($productId, $exclude = false)
    {
        if (empty($productId)) {
            $this->_setIsLoaded(true);
            return $this;
        }
        if (is_array($productId)) {
            if (!empty($productId)) {
                if ($exclude) {
                    $condition = ['nin' => $productId];
                } else {
                    $condition = ['in' => $productId];
                }
            } else {
                $condition = '';
            }
        } else {
            if ($exclude) {
                $condition = ['neq' => $productId];
            } else {
                $condition = $productId;
            }
        }
        $this->addFieldToFilter('entity_id', $condition);
        return $this;
    }

    /**
     * Adding product website names to result collection
     * Add for each product websites information
     *
     * @return $this
     */
    public function addWebsiteNamesToResult()
    {
        $this->needToAddWebsiteNamesToResult = true;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function load($printQuery = false, $logQuery = false)
    {
        if ($this->isLoaded()) {
            return $this;
        }
        parent::load($printQuery, $logQuery);

        if ($this->needToAddWebsiteNamesToResult) {
            $this->doAddWebsiteNamesToResult();
        }
        return $this;
    }

    /**
     * Processs adding product website names to result collection
     *
     * @return $this
     */
    protected function doAddWebsiteNamesToResult()
    {
        $productWebsites = [];
        foreach ($this as $product) {
            $productWebsites[$product->getId()] = [];
        }

        if (!empty($productWebsites)) {
            $select = $this->getConnection()->select()->from(
                ['product_website' => $this->_productWebsiteTable]
            )->join(
                ['website' => $this->getResource()->getTable('store_website')],
                'website.website_id = product_website.website_id',
                ['name']
            )->where(
                'product_website.product_id IN (?)',
                array_keys($productWebsites)
            )->where(
                'website.website_id > ?',
                0
            );

            $data = $this->getConnection()->fetchAll($select);
            foreach ($data as $row) {
                $productWebsites[$row['product_id']][] = $row['website_id'];
            }
        }

        foreach ($this as $product) {
            if (isset($productWebsites[$product->getId()])) {
                $product->setData('websites', $productWebsites[$product->getId()]);
            }
        }
        return $this;
    }

    /**
     * Add store availability filter. Include availability product
     * for store website
     *
     * @param null|string|bool|int|Store $store
     * @return $this
     */
    public function addStoreFilter($store = null)
    {
        if ($store === null) {
            $store = $this->getStoreId();
        }
        $store = $this->_storeManager->getStore($store);

        if ($store->getId() != Store::DEFAULT_STORE_ID) {
            $this->setStoreId($store);
            $this->_productLimitationFilters['store_id'] = $store->getId();
            $this->_applyProductLimitations();
        }

        return $this;
    }

    /**
     * Add website filter to collection
     *
     * @param null|bool|int|string|array $websites
     * @return $this
     */
    public function addWebsiteFilter($websites = null)
    {
        if (!is_array($websites)) {
            $websites = [$this->_storeManager->getWebsite($websites)->getId()];
        }

        $this->_productLimitationFilters['website_ids'] = $websites;
        $this->_applyProductLimitations();

        return $this;
    }

    /**
     * Get filters applied to collection
     *
     * @return array
     */
    public function getLimitationFilters()
    {
        return $this->_productLimitationFilters;
    }

    /**
     * Specify category filter for product collection
     *
     * @param \Magento\Catalog\Model\Category $category
     * @return $this
     */
    public function addCategoryFilter(\Magento\Catalog\Model\Category $category)
    {
        $this->_productLimitationFilters['category_id'] = $category->getId();
        if ($category->getIsAnchor()) {
            unset($this->_productLimitationFilters['category_is_anchor']);
        } else {
            $this->_productLimitationFilters['category_is_anchor'] = 1;
        }

        if ($this->getStoreId() == Store::DEFAULT_STORE_ID) {
            $this->_applyZeroStoreProductLimitations();
        } else {
            $this->_applyProductLimitations();
        }

        return $this;
    }

    /**
     * Join minimal price attribute to result
     *
     * @return $this
     */
    public function joinMinimalPrice()
    {
        $this->addAttributeToSelect('price')->addAttributeToSelect('minimal_price');
        return $this;
    }

    /**
     * Retrieve max value by attribute
     *
     * @param string $attribute
     * @return array|null
     */
    public function getMaxAttributeValue($attribute)
    {
        $select = clone $this->getSelect();
        $attribute = $this->getEntity()->getAttribute($attribute);
        $attributeCode = $attribute->getAttributeCode();
        $tableAlias = $attributeCode . '_max_value';
        $fieldAlias = 'max_' . $attributeCode;
        $condition = 'e.entity_id = ' . $tableAlias . '.entity_id AND ' . $this->_getConditionSql(
            $tableAlias . '.attribute_id',
            $attribute->getId()
        );

        $select->join(
            [$tableAlias => $attribute->getBackend()->getTable()],
            $condition,
            [$fieldAlias => new \Zend_Db_Expr('MAX(' . $tableAlias . '.value)')]
        )->group(
            'e.entity_type_id'
        );

        $data = $this->getConnection()->fetchRow($select);
        if (isset($data[$fieldAlias])) {
            return $data[$fieldAlias];
        }

        return null;
    }

    /**
     * Retrieve ranging product count for arrtibute range
     *
     * @param string $attribute
     * @param int $range
     * @return array
     */
    public function getAttributeValueCountByRange($attribute, $range)
    {
        $select = clone $this->getSelect();
        $attribute = $this->getEntity()->getAttribute($attribute);
        $attributeCode = $attribute->getAttributeCode();
        $tableAlias = $attributeCode . '_range_count_value';

        $condition = 'e.entity_id = ' . $tableAlias . '.entity_id AND ' . $this->_getConditionSql(
            $tableAlias . '.attribute_id',
            $attribute->getId()
        );

        $select->reset(\Magento\Framework\DB\Select::GROUP);
        $select->join(
            [$tableAlias => $attribute->getBackend()->getTable()],
            $condition,
            [
                'count_' . $attributeCode => new \Zend_Db_Expr('COUNT(DISTINCT e.entity_id)'),
                'range_' . $attributeCode => new \Zend_Db_Expr('CEIL((' . $tableAlias . '.value+0.01)/' . $range . ')')
            ]
        )->group(
            'range_' . $attributeCode
        );

        $data = $this->getConnection()->fetchAll($select);
        $res = [];

        foreach ($data as $row) {
            $res[$row['range_' . $attributeCode]] = $row['count_' . $attributeCode];
        }
        return $res;
    }

    /**
     * Retrieve product count by some value of attribute
     *
     * @param string $attribute
     * @return array ($value => $count)
     */
    public function getAttributeValueCount($attribute)
    {
        $select = clone $this->getSelect();
        $attribute = $this->getEntity()->getAttribute($attribute);
        $attributeCode = $attribute->getAttributeCode();
        $tableAlias = $attributeCode . '_value_count';

        $select->reset(\Magento\Framework\DB\Select::GROUP);
        $condition = 'e.entity_id=' . $tableAlias . '.entity_id AND ' . $this->_getConditionSql(
            $tableAlias . '.attribute_id',
            $attribute->getId()
        );

        $select->join(
            [$tableAlias => $attribute->getBackend()->getTable()],
            $condition,
            [
                'count_' . $attributeCode => new \Zend_Db_Expr('COUNT(DISTINCT e.entity_id)'),
                'value_' . $attributeCode => new \Zend_Db_Expr($tableAlias . '.value')
            ]
        )->group(
            'value_' . $attributeCode
        );

        $data = $this->getConnection()->fetchAll($select);
        $res = [];

        foreach ($data as $row) {
            $res[$row['value_' . $attributeCode]] = $row['count_' . $attributeCode];
        }
        return $res;
    }

    /**
     * Return all attribute values as array in form:
     * array(
     *   [entity_id_1] => array(
     *          [store_id_1] => store_value_1,
     *          [store_id_2] => store_value_2,
     *          ...
     *          [store_id_n] => store_value_n
     *   ),
     *   ...
     * )
     *
     * @param string $attribute attribute code
     * @return array
     */
    public function getAllAttributeValues($attribute)
    {
        /** @var $select \Magento\Framework\DB\Select */
        $select = clone $this->getSelect();
        $attribute = $this->getEntity()->getAttribute($attribute);

        $select->reset()->from(
            $attribute->getBackend()->getTable(),
            ['entity_id', 'store_id', 'value']
        )->where(
            'attribute_id = ?',
            (int)$attribute->getId()
        );

        $data = $this->getConnection()->fetchAll($select);
        $res = [];

        foreach ($data as $row) {
            $res[$row['entity_id']][$row['store_id']] = $row['value'];
        }

        return $res;
    }

    /**
     * Get SQL for get record count without left JOINs
     *
     * @return \Magento\Framework\DB\Select
     */
    public function getSelectCountSql()
    {
        return $this->_getSelectCountSql();
    }

    /**
     * Get SQL for get record count
     *
     * @param null $select
     * @param bool $resetLeftJoins
     * @return \Magento\Framework\DB\Select
     */
    protected function _getSelectCountSql($select = null, $resetLeftJoins = true)
    {
        $this->_renderFilters();
        $countSelect = is_null($select) ? $this->_getClearSelect() : $this->_buildClearSelect($select);
        $countSelect->columns('COUNT(DISTINCT e.entity_id)');
        if ($resetLeftJoins) {
            $countSelect->resetJoinLeft();
        }
        return $countSelect;
    }

    /**
     * Prepare statistics data
     *
     * @return $this
     */
    protected function _prepareStatisticsData()
    {
        $select = clone $this->getSelect();
        $priceExpression = $this->getPriceExpression($select) . ' ' . $this->getAdditionalPriceExpression($select);
        $sqlEndPart = ') * ' . $this->getCurrencyRate() . ', 2)';
        $select = $this->_getSelectCountSql($select, false);
        $select->columns(
            [
                'max' => 'ROUND(MAX(' . $priceExpression . $sqlEndPart,
                'min' => 'ROUND(MIN(' . $priceExpression . $sqlEndPart,
                'std' => $this->getConnection()->getStandardDeviationSql('ROUND((' . $priceExpression . $sqlEndPart),
            ]
        );
        $select->where($this->getPriceExpression($select) . ' IS NOT NULL');
        $row = $this->getConnection()->fetchRow($select, $this->_bindParams, \Zend_Db::FETCH_NUM);
        $this->_pricesCount = (int)$row[0];
        $this->_maxPrice = (double)$row[1];
        $this->_minPrice = (double)$row[2];
        $this->_priceStandardDeviation = (double)$row[3];

        return $this;
    }

    /**
     * Retrieve clear select
     *
     * @return \Magento\Framework\DB\Select
     */
    protected function _getClearSelect()
    {
        return $this->_buildClearSelect();
    }

    /**
     * Build clear select
     *
     * @param \Magento\Framework\DB\Select $select
     * @return \Magento\Framework\DB\Select
     */
    protected function _buildClearSelect($select = null)
    {
        if (null === $select) {
            $select = clone $this->getSelect();
        }
        $select->reset(\Magento\Framework\DB\Select::ORDER);
        $select->reset(\Magento\Framework\DB\Select::LIMIT_COUNT);
        $select->reset(\Magento\Framework\DB\Select::LIMIT_OFFSET);
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);

        return $select;
    }

    /**
     * Retrieve all ids for collection
     *
     * @param int|string $limit
     * @param int|string $offset
     * @return array
     */
    public function getAllIds($limit = null, $offset = null)
    {
        $idsSelect = $this->_getClearSelect();
        $idsSelect->columns('e.' . $this->getEntity()->getIdFieldName());
        $idsSelect->limit($limit, $offset);
        $idsSelect->resetJoinLeft();

        return $this->getConnection()->fetchCol($idsSelect, $this->_bindParams);
    }

    /**
     * Retrieve product count select for categories
     *
     * @return \Magento\Framework\DB\Select
     */
    public function getProductCountSelect()
    {
        if ($this->_productCountSelect === null) {
            $this->_productCountSelect = clone $this->getSelect();
            $this->_productCountSelect->reset(
                \Magento\Framework\DB\Select::COLUMNS
            )->reset(
                \Magento\Framework\DB\Select::GROUP
            )->reset(
                \Magento\Framework\DB\Select::ORDER
            )->distinct(
                false
            )->join(
                ['count_table' => $this->getTable('catalog_category_product_index')],
                'count_table.product_id = e.entity_id',
                [
                    'count_table.category_id',
                    'product_count' => new \Zend_Db_Expr('COUNT(DISTINCT count_table.product_id)')
                ]
            )->where(
                'count_table.store_id = ?',
                $this->getStoreId()
            )->group(
                'count_table.category_id'
            );
        }

        return $this->_productCountSelect;
    }

    /**
     * Destruct product count select
     *
     * @return $this
     */
    public function unsProductCountSelect()
    {
        $this->_productCountSelect = null;
        return $this;
    }

    /**
     * Adding product count to categories collection
     *
     * @param \Magento\Eav\Model\Entity\Collection\AbstractCollection $categoryCollection
     * @return $this
     */
    public function addCountToCategories($categoryCollection)
    {
        $isAnchor = [];
        $isNotAnchor = [];
        foreach ($categoryCollection as $category) {
            if ($category->getIsAnchor()) {
                $isAnchor[] = $category->getId();
            } else {
                $isNotAnchor[] = $category->getId();
            }
        }
        $productCounts = [];
        if ($isAnchor || $isNotAnchor) {
            $select = $this->getProductCountSelect();

            $this->_eventManager->dispatch(
                'catalog_product_collection_before_add_count_to_categories',
                ['collection' => $this]
            );

            if ($isAnchor) {
                $anchorStmt = clone $select;
                $anchorStmt->limit();
                //reset limits
                $anchorStmt->where('count_table.category_id IN (?)', $isAnchor);
                $productCounts += $this->getConnection()->fetchPairs($anchorStmt);
                $anchorStmt = null;
            }
            if ($isNotAnchor) {
                $notAnchorStmt = clone $select;
                $notAnchorStmt->limit();
                //reset limits
                $notAnchorStmt->where('count_table.category_id IN (?)', $isNotAnchor);
                $notAnchorStmt->where('count_table.is_parent = 1');
                $productCounts += $this->getConnection()->fetchPairs($notAnchorStmt);
                $notAnchorStmt = null;
            }
            $select = null;
            $this->unsProductCountSelect();
        }

        foreach ($categoryCollection as $category) {
            $_count = 0;
            if (isset($productCounts[$category->getId()])) {
                $_count = $productCounts[$category->getId()];
            }
            $category->setProductCount($_count);
        }

        return $this;
    }

    /**
     * Retrieve unique attribute set ids in collection
     *
     * @return array
     */
    public function getSetIds()
    {
        $select = clone $this->getSelect();
        /** @var $select \Magento\Framework\DB\Select */
        $select->reset(Select::COLUMNS);
        $select->reset(Select::ORDER);
        $select->distinct(true);
        $select->columns('attribute_set_id');
        return $this->getConnection()->fetchCol($select);
    }

    /**
     * Return array of unique product type ids in collection
     *
     * @return array
     */
    public function getProductTypeIds()
    {
        $select = clone $this->getSelect();
        /** @var $select \Magento\Framework\DB\Select */
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->distinct(true);
        $select->columns('type_id');
        return $this->getConnection()->fetchCol($select);
    }

    /**
     * Joins url rewrite rules to collection
     *
     * @return $this
     */
    public function joinUrlRewrite()
    {
        $this->joinTable(
            'url_rewrite',
            'entity_id = entity_id',
            ['request_path'],
            '{{table}}.entity_type = \'' . ProductUrlRewriteGenerator::ENTITY_TYPE . '\'',
            'left'
        );
        return $this;
    }

    /**
     * Add URL rewrites data to product
     * If collection loadded - run processing else set flag
     *
     * @param int|string $categoryId
     * @return $this
     */
    public function addUrlRewrite($categoryId = '')
    {
        $this->_addUrlRewrite = true;
        $useCategoryUrl = $this->_scopeConfig->getValue(
            \Magento\Catalog\Helper\Product::XML_PATH_PRODUCT_URL_USE_CATEGORY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );
        if ($useCategoryUrl) {
            $this->_urlRewriteCategory = $categoryId;
        } else {
            $this->_urlRewriteCategory = 0;
        }

        if ($this->isLoaded()) {
            $this->_addUrlRewrite();
        }

        return $this;
    }

    /**
     * Add URL rewrites to collection
     *
     * @return void
     */
    protected function _addUrlRewrite()
    {
        $productIds = [];
        foreach ($this->getItems() as $item) {
            $productIds[] = $item->getEntityId();
        }
        if (!$productIds) {
            return;
        }

        $select = $this->getConnection()
            ->select()
            ->from(['u' => $this->getTable('url_rewrite')], ['u.entity_id', 'u.request_path'])
            ->where('u.store_id = ?', $this->_storeManager->getStore($this->getStoreId())->getId())
            ->where('u.is_autogenerated = 1')
            ->where('u.entity_type = ?', ProductUrlRewriteGenerator::ENTITY_TYPE)
            ->where('u.entity_id IN(?)', $productIds);

        if ($this->_urlRewriteCategory) {
            $select->joinInner(
                ['cu' => $this->getTable('catalog_url_rewrite_product_category')],
                'u.url_rewrite_id=cu.url_rewrite_id'
            )->where('cu.category_id IN (?)', $this->_urlRewriteCategory);
        }

        // more priority is data with category id
        $urlRewrites = [];

        foreach ($this->getConnection()->fetchAll($select) as $row) {
            if (!isset($urlRewrites[$row['entity_id']])) {
                $urlRewrites[$row['entity_id']] = $row['request_path'];
            }
        }

        foreach ($this->getItems() as $item) {
            if (isset($urlRewrites[$item->getEntityId()])) {
                $item->setData('request_path', $urlRewrites[$item->getEntityId()]);
            } else {
                $item->setData('request_path', false);
            }
        }
    }

    /**
     * Add minimal price data to result
     *
     * @return $this
     */
    public function addMinimalPrice()
    {
        return $this->addPriceData();
    }

    /**
     * Add price data for calculate final price
     *
     * @return $this
     */
    public function addFinalPrice()
    {
        return $this->addPriceData();
    }

    /**
     * Retrieve all ids
     *
     * @param boolean $resetCache
     * @return array
     */
    public function getAllIdsCache($resetCache = false)
    {
        $ids = null;
        if (!$resetCache) {
            $ids = $this->_allIdsCache;
        }

        if (is_null($ids)) {
            $ids = $this->getAllIds();
            $this->setAllIdsCache($ids);
        }

        return $ids;
    }

    /**
     * Set all ids
     *
     * @param array $value
     * @return $this
     */
    public function setAllIdsCache($value)
    {
        $this->_allIdsCache = $value;
        return $this;
    }

    /**
     * Add Price Data to result
     *
     * @param int $customerGroupId
     * @param int $websiteId
     * @return $this
     */
    public function addPriceData($customerGroupId = null, $websiteId = null)
    {
        $this->_productLimitationFilters['use_price_index'] = true;

        if (!isset($this->_productLimitationFilters['customer_group_id']) && is_null($customerGroupId)) {
            $customerGroupId = $this->_customerSession->getCustomerGroupId();
        }
        if (!isset($this->_productLimitationFilters['website_id']) && is_null($websiteId)) {
            $websiteId = $this->_storeManager->getStore($this->getStoreId())->getWebsiteId();
        }

        if (!is_null($customerGroupId)) {
            $this->_productLimitationFilters['customer_group_id'] = $customerGroupId;
        }
        if (!is_null($websiteId)) {
            $this->_productLimitationFilters['website_id'] = $websiteId;
        }

        $this->_applyProductLimitations();

        return $this;
    }

    /**
     * Add attribute to filter
     *
     * @param \Magento\Eav\Model\Entity\Attribute\AbstractAttribute|string $attribute
     * @param array $condition
     * @param string $joinType
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function addAttributeToFilter($attribute, $condition = null, $joinType = 'inner')
    {
        if ($this->isEnabledFlat()) {
            if ($attribute instanceof \Magento\Eav\Model\Entity\Attribute\AbstractAttribute) {
                $attribute = $attribute->getAttributeCode();
            }

            if (is_array($attribute)) {
                $sqlArr = [];
                foreach ($attribute as $condition) {
                    $sqlArr[] = $this->_getAttributeConditionSql($condition['attribute'], $condition, $joinType);
                }
                $conditionSql = '(' . join(') OR (', $sqlArr) . ')';
                $this->getSelect()->where($conditionSql);
                return $this;
            }

            if (!isset($this->_selectAttributes[$attribute])) {
                $this->addAttributeToSelect($attribute);
            }

            if (isset($this->_selectAttributes[$attribute])) {
                $this->getSelect()->where($this->_getConditionSql('e.' . $attribute, $condition));
            }

            return $this;
        }

        $this->_allIdsCache = null;

        if (is_string($attribute) && $attribute == 'is_saleable') {
            $columns = $this->getSelect()->getPart(\Magento\Framework\DB\Select::COLUMNS);
            foreach ($columns as $columnEntry) {
                list($correlationName, $column, $alias) = $columnEntry;
                if ($alias == 'is_saleable') {
                    if ($column instanceof \Zend_Db_Expr) {
                        $field = $column;
                    } else {
                        $connection = $this->getSelect()->getConnection();
                        if (empty($correlationName)) {
                            $field = $connection->quoteColumnAs($column, $alias, true);
                        } else {
                            $field = $connection->quoteColumnAs([$correlationName, $column], $alias, true);
                        }
                    }
                    $this->getSelect()->where("{$field} = ?", $condition);
                    break;
                }
            }

            return $this;
        } else {
            return parent::addAttributeToFilter($attribute, $condition, $joinType);
        }
    }

    /**
     * Add requere tax percent flag for product collection
     *
     * @return $this
     */
    public function addTaxPercents()
    {
        $this->_addTaxPercents = true;
        return $this;
    }

    /**
     * Get require tax percent flag value
     *
     * @return bool
     */
    public function requireTaxPercent()
    {
        return $this->_addTaxPercents;
    }

    /**
     * Adding product custom options to result collection
     *
     * @return $this
     */
    public function addOptionsToResult()
    {
        $productIds = [];
        foreach ($this as $product) {
            $productIds[] = $product->getId();
        }
        if (!empty($productIds)) {
            $options = $this->_productOptionFactory->create()->getCollection()->addTitleToResult(
                $this->_storeManager->getStore()->getId()
            )->addPriceToResult(
                $this->_storeManager->getStore()->getId()
            )->addProductToFilter(
                $productIds
            )->addValuesToResult();

            foreach ($options as $option) {
                if ($this->getItemById($option->getProductId())) {
                    $this->getItemById($option->getProductId())->addOption($option);
                }
            }
        }

        return $this;
    }

    /**
     * Filter products with required options
     *
     * @return $this
     */
    public function addFilterByRequiredOptions()
    {
        $this->addAttributeToFilter('required_options', [['neq' => 1], ['null' => true]], 'left');
        return $this;
    }

    /**
     * Set product visibility filter for enabled products
     *
     * @param array $visibility
     * @return $this
     */
    public function setVisibility($visibility)
    {
        $this->_productLimitationFilters['visibility'] = $visibility;
        $this->_applyProductLimitations();

        return $this;
    }

    /**
     * Add attribute to sort order
     *
     * @param string $attribute
     * @param string $dir
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function addAttributeToSort($attribute, $dir = self::SORT_ORDER_ASC)
    {
        if ($attribute == 'position') {
            if (isset($this->_joinFields[$attribute])) {
                $this->getSelect()->order($this->_getAttributeFieldName($attribute) . ' ' . $dir);
                return $this;
            }
            if ($this->isEnabledFlat()) {
                $this->getSelect()->order("cat_index_position {$dir}");
            }
            // optimize if using cat index
            $filters = $this->_productLimitationFilters;
            if (isset($filters['category_id']) || isset($filters['visibility'])) {
                $this->getSelect()->order('cat_index.position ' . $dir);
            } else {
                $this->getSelect()->order('e.entity_id ' . $dir);
            }

            return $this;
        } elseif ($attribute == 'is_saleable') {
            $this->getSelect()->order("is_saleable " . $dir);
            return $this;
        }

        $storeId = $this->getStoreId();
        if ($attribute == 'price' && $storeId != 0) {
            $this->addPriceData();
            $this->getSelect()->order("price_index.min_price {$dir}");

            return $this;
        }

        if ($this->isEnabledFlat()) {
            $column = $this->getEntity()->getAttributeSortColumn($attribute);

            if ($column) {
                $this->getSelect()->order("e.{$column} {$dir}");
            } elseif (isset($this->_joinFields[$attribute])) {
                $this->getSelect()->order($this->_getAttributeFieldName($attribute) . ' ' . $dir);
            }

            return $this;
        } else {
            $attrInstance = $this->getEntity()->getAttribute($attribute);
            if ($attrInstance && $attrInstance->usesSource()) {
                $attrInstance->getSource()->addValueSortToCollection($this, $dir);
                return $this;
            }
        }

        return parent::addAttributeToSort($attribute, $dir);
    }

    /**
     * Prepare limitation filters
     *
     * @return $this
     */
    protected function _prepareProductLimitationFilters()
    {
        if (isset(
            $this->_productLimitationFilters['visibility']
        ) && !isset(
            $this->_productLimitationFilters['store_id']
        )
        ) {
            $this->_productLimitationFilters['store_id'] = $this->getStoreId();
        }
        if (isset(
            $this->_productLimitationFilters['category_id']
        ) && !isset(
            $this->_productLimitationFilters['store_id']
        )
        ) {
            $this->_productLimitationFilters['store_id'] = $this->getStoreId();
        }
        if (isset(
            $this->_productLimitationFilters['store_id']
        ) && isset(
            $this->_productLimitationFilters['visibility']
        ) && !isset(
            $this->_productLimitationFilters['category_id']
        )
        ) {
            $this->_productLimitationFilters['category_id'] = $this->_storeManager->getStore(
                $this->_productLimitationFilters['store_id']
            )->getRootCategoryId();
        }

        return $this;
    }

    /**
     * Join website product limitation
     *
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _productLimitationJoinWebsite()
    {
        $joinWebsite = false;
        $filters = $this->_productLimitationFilters;
        $conditions = ['product_website.product_id = e.entity_id'];

        if (isset($filters['website_ids'])) {
            $joinWebsite = true;
            if (count($filters['website_ids']) > 1) {
                $this->getSelect()->distinct(true);
            }
            $conditions[] = $this->getConnection()->quoteInto(
                'product_website.website_id IN(?)',
                $filters['website_ids']
            );
        } elseif (isset(
            $filters['store_id']
        ) && (!isset(
            $filters['visibility']
        ) && !isset(
            $filters['category_id']
        )) && !$this->isEnabledFlat()
        ) {
            $joinWebsite = true;
            $websiteId = $this->_storeManager->getStore($filters['store_id'])->getWebsiteId();
            $conditions[] = $this->getConnection()->quoteInto('product_website.website_id = ?', $websiteId);
        }

        $fromPart = $this->getSelect()->getPart(\Magento\Framework\DB\Select::FROM);
        if (isset($fromPart['product_website'])) {
            if (!$joinWebsite) {
                unset($fromPart['product_website']);
            } else {
                $fromPart['product_website']['joinCondition'] = join(' AND ', $conditions);
            }
            $this->getSelect()->setPart(\Magento\Framework\DB\Select::FROM, $fromPart);
        } elseif ($joinWebsite) {
            $this->getSelect()->join(
                ['product_website' => $this->getTable('catalog_product_website')],
                join(' AND ', $conditions),
                []
            );
        }

        return $this;
    }

    /**
     * Join additional (alternative) store visibility filter
     *
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _productLimitationJoinStore()
    {
        $filters = $this->_productLimitationFilters;
        if (!isset($filters['store_table'])) {
            return $this;
        }

        $hasColumn = false;
        foreach ($this->getSelect()->getPart(\Magento\Framework\DB\Select::COLUMNS) as $columnEntry) {
            list(, , $alias) = $columnEntry;
            if ($alias == 'visibility') {
                $hasColumn = true;
            }
        }
        if (!$hasColumn) {
            $this->getSelect()->columns('visibility', 'cat_index');
        }

        $fromPart = $this->getSelect()->getPart(\Magento\Framework\DB\Select::FROM);
        if (!isset($fromPart['store_index'])) {
            $this->getSelect()->joinLeft(
                ['store_index' => $this->getTable('store')],
                'store_index.store_id = ' . $filters['store_table'] . '.store_id',
                []
            );
        }
        if (!isset($fromPart['store_group_index'])) {
            $this->getSelect()->joinLeft(
                ['store_group_index' => $this->getTable('store_group')],
                'store_index.group_id = store_group_index.group_id',
                []
            );
        }
        if (!isset($fromPart['store_cat_index'])) {
            $this->getSelect()->joinLeft(
                ['store_cat_index' => $this->getTable('catalog_category_product_index')],
                join(
                    ' AND ',
                    [
                        'store_cat_index.product_id = e.entity_id',
                        'store_cat_index.store_id = ' . $filters['store_table'] . '.store_id',
                        'store_cat_index.category_id=store_group_index.root_category_id'
                    ]
                ),
                ['store_visibility' => 'visibility']
            );
        }
        // Avoid column duplication problems
        $this->_resourceHelper->prepareColumnsList($this->getSelect());

        $whereCond = join(
            ' OR ',
            [
                $this->getConnection()->quoteInto('cat_index.visibility IN(?)', $filters['visibility']),
                $this->getConnection()->quoteInto('store_cat_index.visibility IN(?)', $filters['visibility'])
            ]
        );

        $wherePart = $this->getSelect()->getPart(\Magento\Framework\DB\Select::WHERE);
        $hasCond = false;
        foreach ($wherePart as $cond) {
            if ($cond == '(' . $whereCond . ')') {
                $hasCond = true;
            }
        }

        if (!$hasCond) {
            $this->getSelect()->where($whereCond);
        }

        return $this;
    }

    /**
     * Join Product Price Table
     *
     * @return $this
     */
    protected function _productLimitationJoinPrice()
    {
        return $this->_productLimitationPrice();
    }

    /**
     * Join Product Price Table with left-join possibility
     *
     * @see \Magento\Catalog\Model\ResourceModel\Product\Collection::_productLimitationJoinPrice()
     * @param bool $joinLeft
     * @return $this
     */
    protected function _productLimitationPrice($joinLeft = false)
    {
        $filters = $this->_productLimitationFilters;
        if (empty($filters['use_price_index'])) {
            return $this;
        }

        $connection = $this->getConnection();
        $select = $this->getSelect();
        $joinCond = join(
            ' AND ',
            [
                'price_index.entity_id = e.entity_id',
                $connection->quoteInto('price_index.website_id = ?', $filters['website_id']),
                $connection->quoteInto('price_index.customer_group_id = ?', $filters['customer_group_id'])
            ]
        );

        $fromPart = $select->getPart(\Magento\Framework\DB\Select::FROM);
        if (!isset($fromPart['price_index'])) {
            $least = $connection->getLeastSql(['price_index.min_price', 'price_index.tier_price']);
            $minimalExpr = $connection->getCheckSql(
                'price_index.tier_price IS NOT NULL',
                $least,
                'price_index.min_price'
            );
            $colls = [
                'price',
                'tax_class_id',
                'final_price',
                'minimal_price' => $minimalExpr,
                'min_price',
                'max_price',
                'tier_price',
            ];
            $tableName = ['price_index' => $this->getTable('catalog_product_index_price')];
            if ($joinLeft) {
                $select->joinLeft($tableName, $joinCond, $colls);
            } else {
                $select->join($tableName, $joinCond, $colls);
            }
            // Set additional field filters
            foreach ($this->_priceDataFieldFilters as $filterData) {
                $select->where(call_user_func_array('sprintf', $filterData));
            }
        } else {
            $fromPart['price_index']['joinCondition'] = $joinCond;
            $select->setPart(\Magento\Framework\DB\Select::FROM, $fromPart);
        }
        //Clean duplicated fields
        $this->_resourceHelper->prepareColumnsList($select);

        return $this;
    }

    /**
     * Apply front-end price limitation filters to the collection
     *
     * @return $this
     */
    public function applyFrontendPriceLimitations()
    {
        $this->_productLimitationFilters['use_price_index'] = true;
        if (!isset($this->_productLimitationFilters['customer_group_id'])) {
            $customerGroupId = $this->_customerSession->getCustomerGroupId();
            $this->_productLimitationFilters['customer_group_id'] = $customerGroupId;
        }
        if (!isset($this->_productLimitationFilters['website_id'])) {
            $websiteId = $this->_storeManager->getStore($this->getStoreId())->getWebsiteId();
            $this->_productLimitationFilters['website_id'] = $websiteId;
        }
        $this->_applyProductLimitations();
        return $this;
    }

    /**
     * Apply limitation filters to collection
     * Method allows using one time category product index table (or product website table)
     * for different combinations of store_id/category_id/visibility filter states
     * Method supports multiple changes in one collection object for this parameters
     *
     * @return $this
     */
    protected function _applyProductLimitations()
    {
        $this->_prepareProductLimitationFilters();
        $this->_productLimitationJoinWebsite();
        $this->_productLimitationJoinPrice();
        $filters = $this->_productLimitationFilters;

        if (!isset($filters['category_id']) && !isset($filters['visibility'])) {
            return $this;
        }

        $conditions = [
            'cat_index.product_id=e.entity_id',
            $this->getConnection()->quoteInto('cat_index.store_id=?', $filters['store_id']),
        ];
        if (isset($filters['visibility']) && !isset($filters['store_table'])) {
            $conditions[] = $this->getConnection()->quoteInto('cat_index.visibility IN(?)', $filters['visibility']);
        }
        $conditions[] = $this->getConnection()->quoteInto('cat_index.category_id=?', $filters['category_id']);
        if (isset($filters['category_is_anchor'])) {
            $conditions[] = $this->getConnection()->quoteInto('cat_index.is_parent=?', $filters['category_is_anchor']);
        }

        $joinCond = join(' AND ', $conditions);
        $fromPart = $this->getSelect()->getPart(\Magento\Framework\DB\Select::FROM);
        if (isset($fromPart['cat_index'])) {
            $fromPart['cat_index']['joinCondition'] = $joinCond;
            $this->getSelect()->setPart(\Magento\Framework\DB\Select::FROM, $fromPart);
        } else {
            $this->getSelect()->join(
                ['cat_index' => $this->getTable('catalog_category_product_index')],
                $joinCond,
                ['cat_index_position' => 'position']
            );
        }

        $this->_productLimitationJoinStore();
        $this->_eventManager->dispatch(
            'catalog_product_collection_apply_limitations_after',
            ['collection' => $this]
        );
        return $this;
    }

    /**
     * Apply limitation filters to collection base on API
     * Method allows using one time category product table
     * for combinations of category_id filter states
     *
     * @return $this
     */
    protected function _applyZeroStoreProductLimitations()
    {
        $filters = $this->_productLimitationFilters;

        $conditions = [
            'cat_pro.product_id=e.entity_id',
            $this->getConnection()->quoteInto('cat_pro.category_id=?', $filters['category_id']),
        ];
        $joinCond = join(' AND ', $conditions);

        $fromPart = $this->getSelect()->getPart(\Magento\Framework\DB\Select::FROM);
        if (isset($fromPart['cat_pro'])) {
            $fromPart['cat_pro']['joinCondition'] = $joinCond;
            $this->getSelect()->setPart(\Magento\Framework\DB\Select::FROM, $fromPart);
        } else {
            $this->getSelect()->join(
                ['cat_pro' => $this->getTable('catalog_category_product')],
                $joinCond,
                ['cat_index_position' => 'position']
            );
        }
        $this->_joinFields['position'] = ['table' => 'cat_pro', 'field' => 'position'];

        return $this;
    }

    /**
     * Add category ids to loaded items
     *
     * @return $this
     */
    public function addCategoryIds()
    {
        if ($this->getFlag('category_ids_added')) {
            return $this;
        }
        $ids = array_keys($this->_items);
        if (empty($ids)) {
            return $this;
        }

        $select = $this->getConnection()->select();

        $select->from($this->_productCategoryTable, ['product_id', 'category_id']);
        $select->where('product_id IN (?)', $ids);

        $data = $this->getConnection()->fetchAll($select);

        $categoryIds = [];
        foreach ($data as $info) {
            if (isset($categoryIds[$info['product_id']])) {
                $categoryIds[$info['product_id']][] = $info['category_id'];
            } else {
                $categoryIds[$info['product_id']] = [$info['category_id']];
            }
        }

        foreach ($this->getItems() as $item) {
            $productId = $item->getId();
            if (isset($categoryIds[$productId])) {
                $item->setCategoryIds($categoryIds[$productId]);
            } else {
                $item->setCategoryIds([]);
            }
        }

        $this->setFlag('category_ids_added', true);
        return $this;
    }

    /**
     * Add tier price data to loaded items
     *
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function addTierPriceData()
    {
        if ($this->getFlag('tier_price_added')) {
            return $this;
        }

        $tierPrices = [];
        $productIds = [];
        foreach ($this->getItems() as $item) {
            $productIds[] = $item->getId();
            $tierPrices[$item->getId()] = [];
        }
        if (!$productIds) {
            return $this;
        }

        /** @var $attribute \Magento\Catalog\Model\ResourceModel\Eav\Attribute */
        $attribute = $this->getAttribute('tier_price');
        if ($attribute->isScopeGlobal()) {
            $websiteId = 0;
        } else {
            if ($this->getStoreId()) {
                $websiteId = $this->_storeManager->getStore($this->getStoreId())->getWebsiteId();
            }
        }

        $connection = $this->getConnection();
        $columns = [
            'price_id' => 'value_id',
            'website_id' => 'website_id',
            'all_groups' => 'all_groups',
            'cust_group' => 'customer_group_id',
            'price_qty' => 'qty',
            'price' => 'value',
            'product_id' => 'entity_id',
        ];
        $select = $connection->select()->from(
            $this->getTable('catalog_product_entity_tier_price'),
            $columns
        )->where(
            'entity_id IN(?)',
            $productIds
        )->order(
            ['entity_id', 'qty']
        );

        if ($websiteId == '0') {
            $select->where('website_id = ?', $websiteId);
        } else {
            $select->where('website_id IN(?)', ['0', $websiteId]);
        }

        foreach ($connection->fetchAll($select) as $row) {
            $tierPrices[$row['product_id']][] = [
                'website_id' => $row['website_id'],
                'cust_group' => $row['all_groups'] ? $this->_groupManagement->getAllCustomersGroup()->getId() : $row['cust_group'],
                'price_qty' => $row['price_qty'],
                'price' => $row['price'],
                'website_price' => $row['price'],
            ];
        }

        /* @var $backend \Magento\Catalog\Model\Product\Attribute\Backend\Tierprice */
        $backend = $attribute->getBackend();

        foreach ($this->getItems() as $item) {
            $data = $tierPrices[$item->getId()];
            if (!empty($data) && $websiteId) {
                $data = $backend->preparePriceData($data, $item->getTypeId(), $websiteId);
            }
            $item->setData('tier_price', $data);
        }

        $this->setFlag('tier_price_added', true);
        return $this;
    }

    /**
     * Add field comparison expression
     *
     * @param string $comparisonFormat - expression for sprintf()
     * @param array $fields - list of fields
     * @return $this
     * @throws \Exception
     */
    public function addPriceDataFieldFilter($comparisonFormat, $fields)
    {
        if (!preg_match('/^%s( (<|>|=|<=|>=|<>) %s)*$/', $comparisonFormat)) {
            throw new \Exception('Invalid comparison format.');
        }

        if (!is_array($fields)) {
            $fields = [$fields];
        }
        foreach ($fields as $key => $field) {
            $fields[$key] = $this->_getMappedField($field);
        }

        $this->_priceDataFieldFilters[] = array_merge([$comparisonFormat], $fields);
        return $this;
    }

    /**
     * Clear collection
     *
     * @return $this
     */
    public function clear()
    {
        foreach ($this->_items as $i => $item) {
            if ($item->hasStockItem()) {
                $item->unsStockItem();
            }
            $this->_items[$i] = null;
        }

        foreach ($this->_itemsById as $i => $item) {
            $this->_itemsById[$i] = null;
        }

        unset($this->_items, $this->_data, $this->_itemsById);
        $this->_data = [];
        return parent::clear();
    }

    /**
     * Set Order field
     *
     * @param string $attribute
     * @param string $dir
     * @return $this
     */
    public function setOrder($attribute, $dir = Select::SQL_DESC)
    {
        if ($attribute == 'price') {
            $this->addAttributeToSort($attribute, $dir);
        } else {
            parent::setOrder($attribute, $dir);
        }
        return $this;
    }

    /**
     * Get products max price
     *
     * @return float
     */
    public function getMaxPrice()
    {
        if (is_null($this->_maxPrice)) {
            $this->_prepareStatisticsData();
        }

        return $this->_maxPrice;
    }

    /**
     * Get products min price
     *
     * @return float
     */
    public function getMinPrice()
    {
        if (is_null($this->_minPrice)) {
            $this->_prepareStatisticsData();
        }

        return $this->_minPrice;
    }

    /**
     * Get standard deviation of products price
     *
     * @return float
     */
    public function getPriceStandardDeviation()
    {
        if (is_null($this->_priceStandardDeviation)) {
            $this->_prepareStatisticsData();
        }

        return $this->_priceStandardDeviation;
    }

    /**
     * Get count of product prices
     *
     * @return int
     */
    public function getPricesCount()
    {
        if (is_null($this->_pricesCount)) {
            $this->_prepareStatisticsData();
        }

        return $this->_pricesCount;
    }
}
