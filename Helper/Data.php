<?php

namespace Alekseon\WidgetForms\Helper;

use Magento\Customer\Model\Session as CustomerSession;
use Alekseon\CustomFormsBuilder\Model\ResourceModel\FormRecord\CollectionFactory as FormRecordCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Url as UrlFrontBuilder;
use Magento\Framework\Url\Encoder as UrlEncoder;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\View\Element\Block\ArgumentInterface;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\LayoutInterface;


class Data extends AbstractHelper implements ArgumentInterface
{
    protected $formRecordCollectionFactory;

    protected $productCollectionFactory;

    protected $orderCollectionFactory;

    protected $customerSession;

    protected $urlEncoder;

    protected $messageManager;

    protected $urlFrontBuilder;

    /** @var \Magento\Framework\View\LayoutInterface  */
    protected $layout;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        FormRecordCollectionFactory $formRecordCollectionFactory,
        ProductCollectionFactory $productCollectionFactory,
        OrderCollectionFactory $orderCollectionFactory,
        CustomerSession $customerSession,
        MessageManagerInterface $messageManager,
        UrlFrontBuilder $urlFrontBuilder,
        UrlEncoder $urlEncoder,
        Context $context,
        LayoutInterface $layout,
        StoreManagerInterface $storeManager,
    ) {
        parent::__construct($context);

        $this->formRecordCollectionFactory = $formRecordCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;

        $this->customerSession = $customerSession;
        $this->messageManager = $messageManager;
        $this->urlFrontBuilder = $urlFrontBuilder;
        $this->urlEncoder = $urlEncoder;
        $this->layout = $layout;
        $this->storeManager = $storeManager;
        $this->urlBuilder = $context->getUrlBuilder();
    }

    public function test () {
        $t = $this->layout->createBlock('Alekseon\WidgetForms\Block\WidgetForm');
        $t->setData('form_id', 1);
        $t->setData('form_mode', 'edit');
        return $t->toHtml();
    }


    public function getCustomerRecords ($additional = false) {
        $result = [];
        if ($this->customerSession->isLoggedIn() && is_numeric($this->customerSession->getCustomerId())) {
            $collection = $this->formRecordCollectionFactory->create();
            $collection
                ->addFieldToFilter('customer_id', ['eq' => $this->customerSession->getCustomerId()]);

            $forms = [];

            if ($collection->getSize()) {

                if ($additional) {
                    $collection->getSelect()->joinLeft(
                        ['ord' => 'sales_order'],
                        'main_table.order_id = ord.entity_id AND main_table.customer_id = ord.customer_id',
                        ['order_increment_id' => 'increment_id']
                    );

                    $collection->getSelect()->joinLeft(
                        ['itm' => 'sales_order_item'],
                        'main_table.order_item_id = itm.item_id AND ord.entity_id = itm.order_id',
                        ['product_name' => 'name']
                    );
                }

                /** @var \Alekseon\CustomFormsBuilder\Model\FormRecord $item */
                foreach ($collection as $item) {
                    $formId = $item->getData('form_id');
                    $form = null;
                    if (!array_key_exists($formId, $forms)) {
                        $form = $item->getForm();
                        $forms[$formId] = $form;
                    } else {
                        $form = $forms[$formId];
                    }
                    $arr = $item->toArray();
                    $arr['form_title'] = $form->getTitle();
                    $result[] = $arr;
                }

            }
        }
        return $result;
    }

    public function getEditUrl (array $item) {
        $params = [
            'form_id'   => $item['form_id'],
            'record_id' => $item['entity_id'],
            'referrer'  => $this->urlEncoder->encode(
                 $this->_getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true])
            ),
            '_secure'   => true
        ];

        foreach (['order_id', 'order_item_id'] as $param) {
            if (array_key_exists($param, $item)) {
                $params[$param] = $item[$param];
            }
        }

        return $this->_getUrl('Alekseon_WidgetForms/form_edit/index', $params);
    }

    public function getFilteredRelatedFormsCollection($filterByCustomer = null, $filterByOrder = null) {

        if (null === $filterByOrder && null === $filterByCustomer) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Unfiltered query is not allowed.'));
        }

        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        $collection = $this->productCollectionFactory->create();
        $collection->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS);
        $collection->getSelect()->columns(['entity_id', 'sku']);

        $attributeCodes = [
            'name',
            'alekseon_related_form',
            'alekseon_form_url_key'
        ];

        // Require both form attributes to be present
        $collection
            ->addAttributeToSelect($attributeCodes, 'left')
            ->addFieldToFilter('alekseon_related_form', ['notnull' => true])
            ->addFieldToFilter('alekseon_form_url_key', ['notnull' => true]);

        $o_collection = $this->orderCollectionFactory->create();
        $o_collection->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS);
        if ($filterByCustomer) {
            if (!is_array($filterByCustomer)) {
                $filterByCustomer = [$filterByCustomer];
            }
            $o_collection->addFieldToFilter('customer_id', ['in' => $filterByCustomer]);
        }

        if ($filterByOrder) {
            if (!is_array($filterByOrder)) {
                $filterByOrder = [$filterByOrder];
                $o_collection->addFieldToFilter('entity_id', ['in' => $filterByOrder]);
            }
        }

        $o_collection->getSelect()->columns(['order_id' => 'entity_id', 'customer_id' => 'customer_id']);

        $o_collection->getSelect()->join(
            ['o_item' => 'sales_order_item'],
            'main_table.entity_id = o_item.order_id',
            ['item_id', 'product_id']
        );

        $o_collection->getSelect()->join(
            ['prd' => new \Zend_Db_Expr('(' . $collection->getSelect() . ')')],
            'o_item.product_id = prd.entity_id',
            ['alekseon_related_form', 'alekseon_form_url_key', 'name', 'sku']
        );

        return $o_collection;
    }

    public function  getRelatedFormsData (
        $filterByCustomer = null,
        $filterByOrder = null
    ) {
        $collection = $this->getFilteredRelatedFormsCollection($filterByCustomer, $filterByOrder);
        return $collection->getConnection()->fetchAll($collection->getSelect());
    }

    public function getCustomerPendingFormsData ($customerId) {
        if (!is_numeric($customerId)) {
            throw new \Exception('Invalid customer id');
        }
        $collection = $this->getFilteredRelatedFormsCollection($customerId);

        $collection->getSelect()->joinLeft(
            ['form_entity' => 'alekseon_custom_form'],
            'prd.alekseon_related_form = form_entity.entity_id',
            ['admin_note']
        );

        $collection->getSelect()->joinLeft(
            ['form_records' => 'alekseon_custom_form_record'],
            'main_table.customer_id = form_records.customer_id AND main_table.entity_id = form_records.order_id',
            []
        );

        $collection->getSelect()->where('form_records.entity_id IS NULL');
        $sqlResult = $collection->getConnection()->fetchAll($collection->getSelect());

        foreach ($sqlResult as &$item) {
            if (array_key_exists('alekseon_form_url_key', $item) && !empty($item['alekseon_form_url_key'])) {
                $item['form_url'] = $this->getFormUrlByKey($item['alekseon_form_url_key'], [
                    'order_id' => $item['order_id'],
                    'order_item_id' => $item['item_id']
                ]);

            } else {
                $item['form_url'] = null;
            }
        }

        return $sqlResult;
    }

    public function getOrderPendingFormsData ($orderId, $customerId = null) {
        if (!is_numeric($orderId)) {
            throw new \Exception('Invalid order id');
        }

        $collection = $this->getFilteredRelatedFormsCollection($customerId, $orderId);

        $collection->getSelect()->joinLeft(
            ['form_entity' => 'alekseon_custom_form'],
            'prd.alekseon_related_form = form_entity.entity_id',
            ['admin_note']
        );

        $collection->getSelect()->joinLeft(
            ['form_records' => 'alekseon_custom_form_record'],
            'main_table.customer_id = form_records.customer_id AND main_table.entity_id = form_records.order_id',
            []
        );
        $collection->getSelect()->where('form_records.entity_id IS NULL');

        $sqlResult = $collection->getConnection()->fetchAll($collection->getSelect());

        foreach ($sqlResult as &$item) {
            if (array_key_exists('alekseon_form_url_key', $item) && !empty($item['alekseon_form_url_key'])) {
                $item['form_url'] = $this->getFormUrlByKey($item['alekseon_form_url_key'], [
                    'order_id' => $item['order_id'],
                    'order_item_id' => $item['item_id']
                ]);

            } else {
                $item['form_url'] = null;
            }
        }

        return $sqlResult;
    }


    public function getFormUrlByKey ($urlKey, $params) {
        $urlKey = trim((string) $urlKey);
        if (!empty($urlKey)) {
            $url = trim($this->urlFrontBuilder->getBaseUrl(), '/');
            $url = $url . '/' . $urlKey;
            $query = [];

            foreach ($params as $key => $value) {
                $k = urlencode($key);
                $v = urlencode($value);
                $query[] = $k . '=' . $v;
            }

            if (!empty($query)) {
                $url = $url . '?' . implode('&', $query);
            }
            return $url;
        }

        return null;
    }

    public function getFormUrlById ($id, $params) {
        // Not working!
        return null;
    }

    public function printFormMessages () {
        if ($this->customerSession->isLoggedIn() && $this->customerSession->getCustomerId()) {
            $data = $this->customerSession->getData('alekseon_form_customer_pending_items');
            $pendingItems = [];
            // There is some data, and it's less than 15 minutes old
            // (we need this as we cannot for example destroy frontend session after order placed from admin)
            if ($data && ($data = json_decode($data, true)) && ($data['timestamp'] + 15*60) < time()) {
                $pendingItems = $data['items'];
            } else {
                // Unset session data
                $this->customerSession->unsetData('alekseon_form_customer_pending_items');
                // Build new one
                $pendingItems = $this->getCustomerPendingFormsData($this->customerSession->getCustomerId());
                $this->customerSession->setData(
                    'alekseon_form_customer_pending_items',
                    json_encode(['items' => $pendingItems, 'timestamp' => time()])
                );
            }

            foreach ($pendingItems as $item) {
                if (empty($item['form_url'])) continue;

                $this->messageManager->addComplexErrorMessage(
                    'pending_form_completion',
                    [
                        'product_name' => $item['name'],
                        'form_url' => $item['form_url'],
                        'form_title' => empty($item['admin_note']) ? __('Link') : $item['admin_note']
                    ]
                );
            }
        }
    }
}
