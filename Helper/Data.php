<?php

namespace Alekseon\WidgetForms\Helper;

use Magento\Customer\Model\Session as CustomerSession;
use Alekseon\CustomFormsBuilder\Model\ResourceModel\FormRecord\CollectionFactory as FormRecordCollectionFactory;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\View\Element\Block\ArgumentInterface;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\LayoutInterface;


class Data extends AbstractHelper implements ArgumentInterface
{
    protected $formRecordCollectionFactory;

    protected $customerSession;

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
        CustomerSession $customerSession,
        Context $context,
        LayoutInterface $layout,
        StoreManagerInterface $storeManager,
    ) {
        parent::__construct($context);

        $this->formRecordCollectionFactory = $formRecordCollectionFactory;
        $this->customerSession = $customerSession;
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


    public function getCustomerRecords () {
        $result = [];
        if ($this->customerSession->isLoggedIn() && is_numeric($this->customerSession->getCustomerId())) {
            $collection = $this->formRecordCollectionFactory->create();
            $collection
                ->addFieldToFilter('customer_id', ['eq' => $this->customerSession->getCustomerId()]);

            $forms = [];

            if ($collection->getSize()) {
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
            '_secure'   => true
        ];

        foreach (['order_id', 'order_item_id'] as $param) {
            if (array_key_exists($param, $item)) {
                $params[$param] = $item[$param];
            }
        }

        return $this->_getUrl('Alekseon_WidgetForms/form_edit/index', $params);
    }


}
