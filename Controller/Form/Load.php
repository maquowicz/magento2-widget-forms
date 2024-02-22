<?php
/*
 * Copyright (c) 2011-2023 PFP Łukasz Maksimowicz
 * https://www.ipfp.pl
 * +48 790 790 543
 *
 * Need help or want to customize anything?
 * Drop us a line at our contact page: https://www.ipfp.pl/contact
 *
 * All title, including but not limited to copyrights, in and to the software product
 * and any copies thereof are owned by PFP Łukasz Maksimowicz.
 * All rights not expressly granted are reserved by PFP Łukasz Maksimowicz.
 *
 * @category Module
 * @package SD
 * @author Łukasz Maksimowicz <maxim@ipfp.pl>
 * @license https://www.ipfp.pl/licenses/magento
 */

namespace Alekseon\WidgetForms\Controller\Form;


use Alekseon\CustomFormsBuilder\Model\FormRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory as OrderItemCollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\Url\Decoder as UrlDecoder;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator as FormkeyValidator;
use Magento\Customer\Model\Session as CustomerSession;

use Magento\Framework\Exception\LocalizedException;

class Load implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    const DEBUG_FLAG = true;

    protected $formRepository;

    protected $orderCollectionFactory;

    protected $orderItemCollectionFactory;

    protected $storeManager;

    protected $directoryList;

    /** @var RequestInterface  */
    protected $request;

    /** @var ResultFactory  */
    protected $resultFactory;

    /** @var UrlInterface  */
    protected $urlBuilder;

    protected $urlDecoder;

    /** @var FormKey  */
    protected $formkey;

    /** @var FormkeyValidator  */
    protected $formkeyValidator;

    /**
     * @var CustomerSession
     */
    protected $customerSession;



    public function __construct(
        FormRepository $formRepository,
        OrderCollectionFactory $orderCollectionFactory,
        OrderItemCollectionFactory $orderItemCollectionFactory,
        StoreManagerInterface $storeManager,
        DirectoryList $directoryList,
        RequestInterface $request,
        ResultFactory $resultFactory,
        UrlInterface $urlBuilder,
        UrlDecoder $urlDecoder,
        Formkey $formkey,
        FormkeyValidator $formkeyValidator,
        CustomerSession $customerSession

    ) {

        $this->formRepository = $formRepository;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderItemCollectionFactory = $orderItemCollectionFactory;
        $this->storeManager = $storeManager;
        $this->directoryList = $directoryList;
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->urlBuilder = $urlBuilder;
        $this->urlDecoder = $urlDecoder;
        $this->formkey = $formkey;
        $this->formkeyValidator = $formkeyValidator;
        $this->customerSession = $customerSession;

    }

    public function execute() {

        $result = [
            'error' => true,
            'is_logged_in' => false,
            'allow_guest_submit' => null,
            'guest_submit_invalidated' => false,
            'missing_params' => [],
            'missing_association' => false,
            'require_login' => false,
            'form_data' => null,
            'messages' => [],
            'redirect_url' => null,
            'debug' => self::DEBUG_FLAG
        ];

        try {
            if (!$this->formkeyValidator->validate($this->request)) {
                $result['require_login'] = true;
                throw new \Exception();
            }

            $result['is_logged_in'] = $this->customerSession->isLoggedIn();

            if (!($formId = $this->request->getParam('form_id')) || !is_numeric($formId)) {
                $result['missing_params'][] = 'form_id';
                throw new \Exception();
            }

            try {
                $form = $this->formRepository->getById($formId);
            } catch (\Throwable $e) {
                throw new \Exception();
            }

            $required = $form->getData('required_record_params') ?? [];
            $supplied = json_decode($this->request->getParam('form_params'), true);

            if (in_array('order_id', $required) &&
                (!array_key_exists('order_id', $supplied) || !is_numeric($supplied['order_id']))
            ) {
                $result['missing_params'][] = 'order_id';
                $result['missing_association'] = true;
            }

            if (in_array('order_item_id', $required) &&
                (!array_key_exists('order_id', $supplied) || !is_numeric($supplied['order_item_id']))
            ) {
                $result['missing_params'][] = 'order_item_id';
                $result['missing_association'] = true;
            }

            if (!($formMode = $this->request->getParam('form_mode')) || !in_array($formMode, ['new', 'edit'])) {
                $result['missing_params'][] = 'form_mode';
            }


            if ('edit' === $formMode && !$result['is_logged_in']) {
                $result['require_login'] = true;
            }

            $result['allow_guest_submit'] = (bool) $form->getData('allow_guest_submit');

            if (!$result['allow_guest_submit'] && !$result['is_logged_in']) {
                $result['require_login'] = true;
            }

            $recordId = array_key_exists('record_id', $supplied) ? $supplied['record_id'] : null;
            if ('edit' === $formMode && !is_numeric($recordId)) {
                $result['missing_params'][] = 'record_id';
            }

            if (in_array('order_id', $required) ) {
                if (!$result['is_logged_in']) {
                    $result['require_login'] = $result['guest_submit_invalidated'] = true;
                }
            }

            if (in_array('order_item_id', $required)) {
                if (!$result['is_logged_in']) {
                    $result['require_login'] = $result['guest_submit_invalidated'] = true;
                }
            }

            $valid = !$result['require_login'] && !$result['missing_association'] && empty($result['missing_params']);
            $order = $orderItem = null;

            if ($valid) {
                if (in_array('order_item_id', $required)) {
                    $orderItemCollection = $this->orderItemCollectionFactory->create();
                    $orderItemCollection
                        ->addFieldToFilter('item_id', ['eq' => $supplied['order_item_id']]);

                    if (!$orderItemCollection->getSize()) {
                        $result['messages'][] = ['type' => 'error', 'text' => 'Cannot locate object.'];
                        $valid = false;
                    } else {
                        /** @var \Magento\Sales\Model\Order\Item $orderItem */
                        $orderItem = $orderItemCollection->getFirstItem();
                        $order = $orderItem->getOrder();

                        if  (!$order || !is_numeric($order->getId())) {
                            $result['messages'][] = ['type' => 'error', 'text' => 'Cannot locate object.'];
                            $valid = false;
                        }

                        if (in_array('order_id', $required) && (int)$supplied['order_id'] !== (int)$order->getId()) {
                            $result['messages'][] = ['type' => 'error', 'text' => __('Object association mismatch.')];
                            $valid = false;
                        }

                        if ((int)$this->customerSession->getCustomerId() !== (int) $order->getCustomerId()) {
                            $result['messages'][] = ['type' => 'error', 'text' => __('Access to object denied.')];
                            $valid = false;
                        }

                    }
                } else if (in_array('order_id', $required)) {
                    $orderCollection = $this->orderCollectionFactory->create();
                    $orderCollection
                        ->addFieldToFilter('entity_id', ['eq' => $supplied['order_id']])
                        ->addFieldToFilter('customer_id', ['eq' => $this->customerSession->getCustomerId()]);

                    if (!$orderCollection->getSize()) {
                        $result['messages'][] = ['type' => 'error', 'text' => __('Cannot locate object.')];
                        $valid = false;
                    } else {
                        $order = $orderCollection->getFirstItem();
                        if ((int) $this->customerSession->getCustomerId() !== (int) $order->getCustomerId()) {
                            $result['messages'][] = ['type' => 'error', 'text' => __('Access to object denied.')];
                            $valid = false;
                        }
                    }
                }
            }

            if ($valid && 'edit' === $formMode) {
                try {
                    /** @var \Alekseon\CustomFormsBuilder\Model\FormRecord $formData */
                    $formData = $form->getRecordById($recordId);
                } catch (\Throwable $e) {
                    $result['messages'][] = ['type' => 'error', 'text' => __('Cannot locate object.')];
                }
                if ((int)$this->customerSession->getCustomerId() !== (int) $formData->getData('customer_id')) {
                    $result['messages'][] = ['type' => 'error', 'text' => __('Access to object denied.')];
                    $valid = false;
                }
                if ($order && (int)$order->getId() !== (int)$formData->getData('order_id')) {
                    $result['messages'][] = ['type' => 'error', 'text' => __('Object association mismatch.')];
                    $valid = false;
                }
                if ($orderItem && (int)$orderItem->getId() !== (int) $formData->getData('order_item_id')) {
                    $result['messages'][] = ['type' => 'error', 'text' => __('Object association mismatch.')];
                    $valid = false;
                }

                if ($valid) {
                    $filtered = array_filter($formData->getData(), function ($key) {
                        return str_starts_with($key, 'field_');
                    }, ARRAY_FILTER_USE_KEY);

                    foreach ($filtered as $code => $value) {
                        // Image data
                        try {
                            $attr = $formData->getAttribute($code);
                            if ($attr) {
                                $type = $attr->getData('frontend_input');
                                if ('image' === $type) {
                                    $path = $this->directoryList->getPath(DirectoryList::MEDIA) . '/' . $value;
                                    if (is_file($path) && is_readable($path)) {
                                        $mediaUrl = $this ->storeManager
                                            ->getStore()
                                            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

                                        $filtered[$code] = $mediaUrl . $value;
                                    }
                                }
                            }
                        } catch(\Throwable $e) {

                        }
                    }

                    $result['data'] = $filtered;
                }

            }

            $result['error'] = !$valid;

        } catch (\Throwable $e) {
            $message = 'Something went wrong.';
            if ($e instanceof LocalizedException && strlen(trim($e->getMessage()))) {
                $message = $e->getMessage();
            }
            $result['error'] = true;
            $result['messages'][] = ['type' => 'error', 'text' => $message];

        }
        if (!self::DEBUG_FLAG) {
            unset($result['missing_params']);
        }
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($result);

        return $resultJson;

    }
}
