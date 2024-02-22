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

namespace Alekseon\WidgetForms\Controller\Form\Edit;


use Alekseon\CustomFormsBuilder\Model\FormRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory as OrderItemCollectionFactory;

use Magento\Framework\View\LayoutInterface;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator as FormkeyValidator;
use Magento\Customer\Model\Session as CustomerSession;

use Magento\Framework\Exception\LocalizedException;

class Index implements \Magento\Framework\App\Action\HttpGetActionInterface
{
    protected $formRepository;

    protected $orderCollectionFactory;

    protected $orderItemCollectionFactory;

    protected $layout;

    /** @var RequestInterface  */
    protected $request;

    protected $messageManager;

    /** @var ResultFactory  */
    protected $resultFactory;

    /** @var UrlInterface  */
    protected $urlBuilder;

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
        LayoutInterface $layout,
        RequestInterface $request,
        MessageManagerInterface $messageManager,
        ResultFactory $resultFactory,
        UrlInterface $urlBuilder,
        Formkey $formkey,
        FormkeyValidator $formkeyValidator,
        CustomerSession $customerSession

    ) {

        $this->formRepository = $formRepository;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderItemCollectionFactory = $orderItemCollectionFactory;
        $this->layout = $layout;

        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->resultFactory = $resultFactory;
        $this->urlBuilder = $urlBuilder;
        $this->formkey = $formkey;
        $this->formkeyValidator = $formkeyValidator;
        $this->customerSession = $customerSession;

    }

    public function execute() {

        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $referrer = $this->urlBuilder->getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true]);

        // Customer must be logged in
        if (!$this->customerSession->isLoggedIn()) {
            $this->messageManager->addNoticeMessage(__('Please log in to complete your request.'));

            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl(
                $this->urlBuilder->getUrl('customer/account/login', [
                    '_secure' => true,
                    'referer' => base64_encode($referrer)
                ])
            );

            return $resultRedirect;
        }

        // Params required in any case
        $formId = $this->request->getParam('form_id');
        $recordId = $this->request->getParam('record_id');

        if (!is_numeric($formId)) {
            $this->messageManager->addErrorMessage(__('Invalid request.'));
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl(
                $this->urlBuilder->getBaseUrl(),
                ['_secure' => true]
            );

            return $resultRedirect;
        }

        if (!is_numeric($recordId)) {
            $this->messageManager->addErrorMessage(__('Invalid request.'));
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl(
                $this->urlBuilder->getBaseUrl(),
                ['_secure' => true]
            );
            return $resultRedirect;
        }

        $form = $record = null;

        try {
            $form   = $this->formRepository->getById($formId);
        } catch (\Throwable $e) {
            // Form not found
            $this->messageManager->addErrorMessage(__('Invalid request.'));
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl(
                $this->urlBuilder->getBaseUrl(),
                ['_secure' => true]
            );
            return $resultRedirect;
        }



        try {
            $record = $form->getRecordById($recordId);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Invalid request.'));
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl(
                $this->urlBuilder->getBaseUrl(),
                ['_secure' => true]
            );
            return $resultRedirect;
        }

        $customerId = (int) $this->customerSession->getCustomerId();

        // Record does not have proper customer_id
        if (!$customerId || (int) $record->getData('customer_id') !== $customerId) {
            $this->messageManager->addErrorMessage(__('Invalid request.'));
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl(
                $this->urlBuilder->getBaseUrl(),
                ['_secure' => true]
            );
            return $resultRedirect;
        }

        $requiredRecordParams = (array) $form->getData('required_record_params');

        if ((in_array('order_id', $requiredRecordParams) && !is_numeric($record->getData('order_id'))) ||
            (in_array('order_item_id', $requiredRecordParams) && !is_numeric($record->getData('order_item_id')))
        ) {
            $this->messageManager->addErrorMessage(__('Invalid request.'));
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl(
                $this->urlBuilder->getBaseUrl(),
                ['_secure' => true]
            );
            return $resultRedirect;
        }

        $order = $orderItem = null;

        if (in_array('order_id', $requiredRecordParams) || in_array('order_item_id', $requiredRecordParams)) {
            $collection = $this->orderCollectionFactory->create();
            $collection->addFieldToFilter('customer_id', ['eq' => $customerId]);

            if (in_array('order_id', $requiredRecordParams)) {
                $collection->addFieldToFilter('entity_id', ['eq' => $record->getData('order_id')]);
            }

            if (in_array('order_item_id', $requiredRecordParams)) {
                $collection->getSelect()->join(
                    ['o_item' => 'sales_order_item'],
                    'main_table.entity_id = o_item.order_id',
                    []
                );
            }

            if (!$collection->getSize()) {
                $this->messageManager->addErrorMessage(__('Invalid request.'));
                $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                $resultRedirect->setUrl(
                    $this->urlBuilder->getBaseUrl(),
                    ['_secure' => true]
                );
                return $resultRedirect;
            }
        }

        /** @var \Magento\Framework\View\Element\Template $container */
        $block = $this->layout->getBlock('alekseon_form');

        $block->setData('form_id', $this->request->getParam('form_id'));
        $block->setData('form_mode', 'edit');


        return $resultPage;

    }
}
