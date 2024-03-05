<?php
/**
 * Copyright © Alekseon sp. z o.o.
 * http://www.alekseon.com/
 */
declare(strict_types=1);

namespace Alekseon\WidgetForms\Controller\Form;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;


/**
 * Class Submit
 * @package Alekseon\WidgetForms\Controller
 */
class Submit implements HttpPostActionInterface
{
    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;
    /**
     * @var \Magento\Framework\App\ResponseInterface
     */
    private $response;
    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $eventManager;
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $jsonFactory;
    /**
     * @var \Alekseon\CustomFormsBuilder\Model\FormRepository
     */
    private $formRepository;
    /**
     * @var \Alekseon\CustomFormsBuilder\Model\FormRecordFactory
     */
    private $formRecordFactory;

    private $directoryList;

    private $urlBuilder;

    private $urlDecoder;


    private $formRecordCollectionFactory;

    private $orderCollectionFactory;

    private $orderItemCollectionFactory;

    private $customerSession;
    /**
     * @var \Magento\Framework\Data\Form\FormKey\Validator
     */
    private $formKeyValidator;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Submit constructor.
     * @param Context $context
     */
    public function __construct(
        Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Alekseon\CustomFormsBuilder\Model\FormRepository $formRepository,
        \Alekseon\CustomFormsBuilder\Model\FormRecordFactory $formRecordFactory,
        \Alekseon\CustomFormsBuilder\Model\ResourceModel\FormRecord\CollectionFactory $formRecordCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory $orderItemCollectionFactory,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Url\Decoder $urlDecoder,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->request = $context->getRequest();
        $this->response = $context->getResponse();
        $this->eventManager = $context->getEventManager();
        $this->formRecordFactory = $formRecordFactory;
        $this->formRecordCollectionFactory = $formRecordCollectionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderItemCollectionFactory = $orderItemCollectionFactory;
        $this->customerSession = $customerSession;
        $this->directoryList = $directoryList;
        $this->urlBuilder = $urlBuilder;
        $this->urlDecoder = $urlDecoder;
        $this->jsonFactory = $jsonFactory;
        $this->formRepository = $formRepository;
        $this->formKeyValidator = $formKeyValidator;
        $this->logger = $logger;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        $resultJson = $this->jsonFactory->create();

        // todo : refactor this mess

        try {
            $form = $this->getForm();
            $this->validateData();
            $post = $this->getRequest()->getPost();

            $additional = $this->getRequest()->getParam('additional_params');
            if ($additional) {
                $additional = json_decode($additional, true);
            }

            $formMode = $this->getRequest()->getParam('form_mode');
            $allowGuestSubmit = (bool) $form->getData('allow_guest_submit');
            $isLoggedIn = $this->customerSession->isLoggedIn();

            if (('edit' === $formMode || false === $allowGuestSubmit) && !$isLoggedIn) {
                throw new LocalizedException(__('You were logged out. Please log in.'));
            }

            $recordId = null;

            if ('edit' === $formMode) {
                if (!$additional ||
                    !array_key_exists('record_id', $additional) ||
                    !is_numeric($additional['record_id'])) {

                    throw new LocalizedException(__('Invalid request.'));
                }
                $recordId = $additional['record_id'];
            }

            $requiredParams = $form->getData('required_record_params') ?
                $form->getData('required_record_params') : null;

            if ($requiredParams && (in_array('order_id', $requiredParams) || in_array('order_item_id', $requiredParams))) {
                if (!$isLoggedIn) {
                    throw new LocalizedException(__('You were logged out. Please log in.'));
                }
            }

            if (in_array('order_id', $requiredParams)) {
                if (!$additional ||
                    !array_key_exists('order_id', $additional) ||
                    !is_numeric($additional['order_id'])) {

                    throw new LocalizedException(__('Invalid request.'));
                }
            }

            if (in_array('order_id', $requiredParams)) {
                if (!$additional ||
                    !array_key_exists('order_item_id', $additional) ||
                    !is_numeric($additional['order_item_id'])) {

                    throw new LocalizedException(__('Invalid request.'));
                }
            }

            $order = $orderItem = null;

            if (in_array('order_item_id', $requiredParams)) {
                $orderItemCollection = $this->orderItemCollectionFactory->create();
                $orderItemCollection
                    ->addFieldToFilter('item_id', $additional['order_item_id']);

                if (in_array('order_id', $requiredParams)) {
                    $orderItemCollection
                        ->addFieldToFilter('order_id', ['eq' => $additional['order_id']]);
                }

                if (!$orderItemCollection->getSize()) {
                    throw new LocalizedException(__('Invalid request'));
                }

                /** @var \Magento\Sales\Model\Order\Item $orderItem */
                $orderItem = $orderItemCollection->getFirstItem();

                $order = $orderItem->getOrder();
                if ((!$order || !$order->getId())) {
                    throw new LocalizedException(__('Invalid request'));
                }

                if (array_key_exists('order_id', $requiredParams) &&
                    (int)$order->getId() !== (int) $additional['order_id']) {
                    throw new LocalizedException(__('Invalid request'));
                }

                if ((int)$this->customerSession->getCustomerId() !== (int) $order->getCustomerId()) {
                    throw new LocalizedException(__('Invalid request'));
                }
            } else if (in_array('order_id', $requiredParams)) {
                $orderCollection = $this->orderCollectionFactory->create();
                $orderCollection
                    ->addFieldToFilter('entity_id', ['eq' => $additional['order_id']]);

                if (!$orderCollection->getSize()) {
                    throw new LocalizedException(__('Invalid request'));
                }

                $order = $orderCollection->getFirstItem();
                if ((int)$this->customerSession->getCustomerId() !== (int) $order->getCustomerId()) {
                    throw new LocalizedException(__('Invalid request'));
                }
            }

            $formRecord = null;

            if ('edit' === $formMode) {
                if ($order) {
                    $allowedStatuses = $form->getData('allow_form_edit_for_order_statuses');
                    $allowedStatuses = is_array($allowedStatuses) ? $allowedStatuses : [];

                    if (!in_array($order->getStatus(), $allowedStatuses)) {
                        throw new LocalizedException(__('Edition of this form is no longer possible.'));
                    }
                }
                $formRecordCollection = $this->formRecordCollectionFactory->create();
                $formRecordCollection
                    ->addFieldToFilter('entity_id', ['eq' => $recordId])
                    ->addFieldToFilter('customer_id', ['eq' => $this->customerSession->getCustomerId()]);

                if (in_array('order_id', $requiredParams)) {
                    $formRecordCollection
                        ->addFieldToFilter('order_id', ['eq' => $additional['order_id']]);
                }
                if (in_array('order_item_id', $requiredParams)) {
                    $formRecordCollection
                        ->addFieldToFilter('order_item_id', ['eq' => $additional['order_item_id']]);
                }

                if (!$formRecordCollection->getSize()) {
                    throw new LocalizedException(__('Invalid request'));
                }
                $formRecord = $formRecordCollection->getFirstItem();

                $formRecord->getResource()->setCurrentForm($form);
                $formRecord->setStoreId($form->getStoreId());
                $formRecord->setFormId($form->getId());
                $formRecord->getResource()->load($formRecord, $formRecord->getId());
            } else {

                // Condition hint: guest submit gets invalidate by order_id/order_item_id requirement
                if (false && !$form->getData('allow_multiple_submits') && (!$form->getData('allow_guest_submit') ||
                    in_array('order_id', $requiredParams) || in_array('order_item_id', $requiredParams))) {

                    $formRecordCollection = $this->formRecordCollectionFactory->create();

                    $formRecordCollection->addFieldToFilter('form_id', $form->getId());
                    $formRecordCollection->addFieldToFilter(
                        'customer_id', ['eq' => $this->customerSession->getCustomerId()]
                    );

                    if ($additional['order_id']) {
                        $formRecordCollection->addFieldToFilter('order_id', ['eq' => $additional['order_id']]);
                    }
                    if ($additional['order_item_id']) {
                        $formRecordCollection->addFieldToFilter('order_item_id', ['eq' => $additional['order_item_id']]);
                    }

                    if ($formRecordCollection->getSize()) {
                        throw new LocalizedException(__('You have already submitted this form.'));
                    }


                }

                $formRecord = $this->formRecordFactory->create();
                $formRecord->setData('customer_id', $this->customerSession->getCustomerId());
                $formRecord->setData('customer_email', $this->customerSession->getCustomer()->getEmail());

                if (in_array('order_id', $requiredParams)) {
                    $formRecord->setData('order_id', $additional['order_id']);
                }
                if (in_array('order_item_id', $requiredParams)) {
                    $formRecord->setData('order_item_id', $additional['order_item_id']);
                }

                $formRecord->getResource()->setCurrentForm($form);
                $formRecord->setStoreId($form->getStoreId());
                $formRecord->setFormId($form->getId());
            }


            $formFields = $form->getFieldsCollection();
            foreach ($formFields as $field) {
                $fieldCode = $field->getAttributeCode();

                $value = $post[$fieldCode] ?? $field->getDefaultValue();

                // Check for old image, if it exists set old value on  formRecord - it's resource model will delete old file
                // in after save method
                if ($formRecord->getId()) {
                    $attribute = $formRecord->getAttribute($fieldCode);
                    if ($attribute && 'image' === $attribute->getData('frontend_input')) {
                        $oldValue = $formRecord->getData($fieldCode);
                        // Change it to file_exists check
                        if (!empty($oldValue)) {
                            $path = $this->directoryList->getPath(DirectoryList::MEDIA) . '/' . $oldValue;
                            if (is_file($path)) {
                                $value = $oldValue;
                            }
                        }
                    }
                }

                $formRecord->setData($fieldCode, $value);
            }

            $formRecord->getResource()->save($formRecord);
            $this->eventManager->dispatch('alekseon_widget_form_after_submit', ['form_record' => $formRecord]);

            $referrer = $this->getRequest()->getParam('referrer');
            if ($referrer) {
                $referrer = $this->urlDecoder->decode($referrer);
            }

            $payload = [
                'title' => $this->getSuccessTitle($formRecord),
                'message' => $this->getSuccessMessage($formRecord),
            ];
            if ($referrer && filter_var($referrer, FILTER_VALIDATE_URL)) {
                if (str_starts_with($referrer, $this->urlBuilder->getBaseUrl())) {
                    $payload['redirect_url'] = $referrer;
                }
            }
            $payload['errors'] = false;
            $resultJson->setData(
                $payload
            );
            if ('new' === $formMode) {
                $this->customerSession->unsetData('alekseon_form_customer_pending_items');
            }

        } catch (LocalizedException $e) {
            $resultJson->setData(
                [
                    'errors' => true,
                    'message' => $e->getMessage()
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error('Widget Form Error during submit action: ' . $e->getMessage());
            $resultJson->setData(
                [
                    'errors' => true,
                    'message' => __('We are unable to process your request. Please, try again later.'),
                ]
            );
        }

        return $resultJson;
    }

    /**
     * @param $form
     * @return string
     */
    public function getSuccessMessage($formRecord)
    {
        $successMessage = $formRecord->getForm()->getFormSubmitSuccessMessage();
        if (!$successMessage) {
            $successMessage = __('Thank You!');
        }
        return (string) $successMessage;
    }

    /**
     * @param $form
     * @return string
     */
    public function getSuccessTitle($formRecord)
    {
        $successTitle = $formRecord->getForm()->getFormSubmitSuccessTitle();
        if (!$successTitle) {
            $successTitle = __('Success');
        }
        return (string) $successTitle;
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function validateData()
    {
        if (!$this->formKeyValidator->validate($this->getRequest())) {
            throw new LocalizedException(__('Your session has probably expired. Please refresh the page.'));
        }

        if ($this->getRequest()->getParam('hideit')) {
            throw new LocalizedException(__('Interrupted Data'));
        }

        if (!$this->getRequest()->getParam('form_mode') || !in_array($this->getRequest()->getParam('form_mode'), ['new', 'edit'])) {
            throw new LocalizedException(__('Unknown form mode.'));
        }
    }

    /**
     *
     */
    public function getForm()
    {
        $formId = $this->getRequest()->getParam('form_id');
        $form = $this->formRepository->getById($formId);
        return $form;
    }

    /**
     * @return \Magento\Framework\App\RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }
}
