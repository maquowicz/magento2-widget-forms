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
    protected $formRepository;

    protected $orderCollectionFactory;

    protected $orderItemCollectionFactory;

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
            'require_login' => false,
            'form_data' => null,
            'messages' => [],
            'redirect_url' => null
        ];

        try {
            if (!$this->formkeyValidator->validate($this->request)) {
                throw new LocalizedException(__('Your session has expired.'));
            }

            $result['is_logged_in'] = $this->customerSession->isLoggedIn();

            if (!($formMode = $this->request->getParam('form_mode')) || !in_array($formMode, ['new', 'edit'])) {
                $result['missing_params'][] = 'form_mode';
                throw new LocalizedException(__('Missing parameter.'));
            }


            if ('edit' === $formMode && !$result['is_logged_in']) {
                $result['require_login'] = true;
                throw new LocalizedException(__('Please log in.'));
            }

            if (!($formId = $this->request->getParam('form_id'))) {
                $result['missing_params'][] = 'form_id';
                throw new LocalizedException(__('Missing parameter.'));
            }

            $supplied = json_decode($this->request->getParam('form_params'), true);

            $recordId = array_key_exists('record_id', $supplied) ? $supplied['record_id'] : null;
            if ('edit' === $formMode && !is_numeric($recordId)) {
                $result['missing_params'][] = 'record_id';
                throw new LocalizedException(__('Missing parameter.'));
            }

            try {
                $form = $this->formRepository->getById($formId);
            } catch (\Throwable $e) {
                throw new LocalizedException(__());
            }

            $required = $form->getData('required_record_params') ?? [];
            $result['allow_guest_submit'] = (bool) $form->getData('allow_guest_submit');

            if (!$result['allow_guest_submit'] && !$result['is_logged_in']) {
                $result['require_login'] = true;
                throw new LocalizedException(__('Please log in.'));
            }


            $valid = true;

            if (in_array('order_id', $required) ) {
                if (!$result['is_logged_in']) {
                    $result['guest_submit_invalidated'] = true;
                    $result['messages'][] = 'Please log in.';
                    $valid = false;
                } else if (!array_key_exists('order_id', $supplied) || !is_numeric($supplied['order_id'])) {
                    $result['messages'][] = 'Missing parameter.';

                    $result['missing_params'][] = 'order_id';
                    $valid = false;
                }
            }

            if (in_array('order_item_id', $required)) {
                if (!$result['is_logged_in']) {
                    $result['guest_submit_invalidated'] = true;
                    $result['messages'][] = 'Please log in.';
                    $valid = false;
                } else if (!array_key_exists('order_item_id', $supplied) || !is_numeric($supplied['order_item_id'])) {
                    $result['messages'][] = 'Missing parameter';
                    $result['missing_params'][] = 'order_item_id';
                    $valid = false;
                }
            }

            $order = $orderItem = null;

            if ($valid) {
                if (in_array('order_item_id', $required)) {
                    $orderItemCollection = $this->orderItemCollectionFactory->create();
                    $orderItemCollection
                        ->addFieldToFilter('item_id', ['eq' => $supplied['order_item_id']]);

                    if (!$orderItemCollection->getSize()) {
                        $result['messages'][] = 'Cannot locate order_item';
                        $valid = false;
                    } else {
                        /** @var \Magento\Sales\Model\Order\Item $orderItem */
                        $orderItem = $orderItemCollection->getFirstItem();
                        $order = $orderItem->getOrder();

                        if  (!$order || !is_numeric($order->getId())) {
                            $result['messages'][] = 'Cannot locate order';
                            $valid = false;
                        }

                        if (in_array('order_id', $required) && (int)$supplied['order_id'] !== (int)$order->getId()) {
                            $result['messages'][] = 'Order/Item mismatch';
                            $valid = false;
                        }

                        if ((int)$this->customerSession->getCustomerId() !== (int) $order->getCustomerId()) {
                            $result['messages'][] = 'Access to object denied (order)';
                            $valid = false;
                        }

                    }
                } else if (in_array('order_id', $required)) {
                    $orderCollection = $this->orderCollectionFactory->create();
                    $orderCollection
                        ->addFieldToFilter('entity_id', ['eq' => $supplied['order_id']])
                        ->addFieldToFilter('customer_id', ['eq' => $this->customerSession->getCustomerId()]);

                    if (!$orderCollection->getSize()) {
                        $result['messages'][] = 'Cannot locate order.';
                        $valid = false;
                    } else {
                        $order = $orderCollection->getFirstItem();
                        if ((int) $this->customerSession->getCustomerId() !== (int) $order->getCustomerId()) {
                            $result['messages'][] = 'Access to object denied (order)';
                            $valid = false;
                        }
                    }
                }
            }

            if ($valid && 'edit' === $formMode) {
                try {
                    $formData = $form->getRecordById($recordId);
                } catch (\Throwable $e) {
                    $result['messages'][] = 'Cannot locate object (record)';
                }
                if ((int)$this->customerSession->getCustomerId() !== (int) $formData->getData('customer_id')) {
                    $result['messages'][] = 'Access to object denied (record)';
                    $valid = false;
                }
                if ($order && (int)$order->getId() !== (int)$formData->getData('order_id')) {
                    $result['messages'][] = 'Order/Record mismatch';
                    $valid = false;
                }
                if ($orderItem && (int)$orderItem->getId() !== (int) $formData->getData('order_item_id')) {
                    $result['messages'][] = 'Item/Record mismatch';
                    $valid = false;
                }

                if ($valid) {
                    $filtered = array_filter($formData->getData(), function ($key) {
                        return str_starts_with($key, 'field_');
                    }, ARRAY_FILTER_USE_KEY);

                    $result['data'] = $filtered;
                }

            }


            $result['error'] = !$valid;

        } catch (\Throwable $e) {
            $message = 'Something went wrong';
            if ($e instanceof LocalizedException && strlen(trim($e->getMessage()))) {
                $message = $e->getMessage();
            }
            $result['error'] = true;
            $result['messages'][] = ['type' => 'error', 'text' => $message];

        }

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($result);

        return $resultJson;

    }
}
