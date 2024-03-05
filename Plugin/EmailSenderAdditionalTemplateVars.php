<?php
/**
 * Copyright © Alekseon sp. z o.o.
 * http://www.alekseon.com/
 */
declare(strict_types=1);

namespace Alekseon\WidgetForms\Plugin;

/**
 * Class AddFormFieldsetWarningPlugin
 * @package Alekseon\WidgetForms\Plugin
 */
class EmailSenderAdditionalTemplateVars
{
    protected $customerCollectionFactory;

    protected $orderCollectionFactory;

    protected $frontendUrlBuilder;

    protected $frontendBlocksRepository;

    protected $templateFilter;

    /** @var \Alekseon\CustomFormsBuilder\Model\FormRecord $formRecord */
    protected $formRecord = null;

    public function __construct(
        \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Framework\Url $frontendUrlBuilder,
        \Alekseon\CustomFormsFrontend\Model\FrontendBlocksRepository $frontendBlocksRepository,
        \Alekseon\CustomFormsFrontend\Model\Template\Filter $templateFilter

    ) {
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->frontendUrlBuilder = $frontendUrlBuilder;
        $this->frontendBlocksRepository = $frontendBlocksRepository;
        $this->templateFilter = $templateFilter;
    }

    public function afterGetTemplateParams (
        \Alekseon\CustomFormsEmailNotification\Model\Email\AbstractSender $subject,
        array $result
    ) {
        // todo : in case of asynchronous bulk email sending (cron) alekseon's logic (querying db separately for every email we're planning to send)
        // todo : leaves a lot to be desired, change it in the future

        $customerId = $orderId = null;

        $result['boundToAccount'] = $result['boundToOrder'] = $result['boundToItem'] = $result['boundToProduct'] = $result['canEditForm'] = false;

        $formEditParams = [];

        if (!array_key_exists('customer', $result) && $this->formRecord) {
            if (($customerId = $this->formRecord->getData('customer_id')) && is_numeric($customerId)) {
                try {
                    $customerCollection = $this->customerCollectionFactory->create();
                    $customerCollection->addFieldToFilter(
                        'entity_id',
                        ['eq' => $customerId]
                    );

                    if ($customerCollection->getSize()) {
                        /** @var \Magento\Customer\Model\Customer $customerModel */
                        $customerModel = $customerCollection->getFirstItem();
                        $customerModel->setData('name', $customerModel->getName());
                        $result['customer'] = $customerModel;
                        $result['boundToAccount'] = true;
                    }

                    $formEditParams['form_id'] = $this->formRecord->getForm()->getId();
                    $formEditParams['record_id'] = $this->formRecord->getId();

                } catch (\Throwable $e) {}
            }
        }

        if (is_numeric($customerId) && !array_key_exists('order', $result) && $this->formRecord) {
            if (($orderId = $this->formRecord->getData('order_id')) && is_numeric($orderId)) {
                $orderCollection = $this->orderCollectionFactory->create();

                $orderCollection
                    ->addFieldToFilter('customer_id', ['eq' => $customerId])
                    ->addFieldToFilter('entity_id', ['eq' => $orderId]);

                if ($orderCollection->getSize()) {
                    /** @var \Magento\Sales\Model\Order $orderModel */
                    $orderModel = $orderCollection->getFirstItem();
                    $result['order'] = $orderModel;
                    $result['boundToOrder'] = true;

                    $formEditParams['order_id'] = $orderModel->getId();

                    $result['orderUrl'] = $this->frontendUrlBuilder->getUrl(
                        'sales/order/view',
                        ['_secure' => true, 'order_id' => $orderModel->getId()]
                    );


                    if (($orderItemId = $this->formRecord->getData('order_item_id')) && is_numeric($orderItemId)) {
                        /** @var \Magento\Sales\Model\Order\Item $orderItemModel */
                        $orderItemModel = $orderModel->getItemById($orderItemId);

                        if ($orderItemModel && $orderModel->getId()) {
                            $result['order_item'] = $orderItemModel;
                            $result['boundToItem'] = true;

                            $formEditParams['order_item_id'] = $orderItemModel->getId();
                            /** @var \Magento\Catalog\Model\Product $productModel */
                            $productModel = $orderItemModel->getProduct();

                            if ($productModel && $productModel->getId()) {
                                $result['boundToProduct'] = true;
                                $result['product'] = $productModel;
                                $result['productUrl'] = $productModel->getProductUrl();
                            }

                        }
                    }
                }
            }
        }

        if (!empty($formEditParams) && $this->validateFormEditParams($formEditParams)) {
            $formEditParams['_secure'] = true;
            $result['formEditUrl'] = $this->frontendUrlBuilder->getUrl(
                'widgetForm/form_edit/index',
                $formEditParams
            );
            $result['canEditForm'] = true;
        }

        $this->getTemplateParamsWithRecordHtml($result);

        return $result;
    }

    public function afterSetFormRecord (
        \Alekseon\CustomFormsEmailNotification\Model\Email\AbstractSender $subject,
        \Alekseon\CustomFormsEmailNotification\Model\Email\AbstractSender $result,
        \Alekseon\CustomFormsBuilder\Model\FormRecord $formRecord
    ) {
        $this->formRecord = $formRecord;
        return $result;
    }

    protected function getTemplateParamsWithRecordHtml(&$templateParams)
    {
        if (isset($templateParams['record'])) {
            $formRecord = $templateParams['record'];
            $attributes = $formRecord->getResource()->getAllLoadedAttributes();
            $recordHtml = '<div class="form-record">';
            foreach ($attributes as $attribute) {
                if ($formRecord->getData($attribute->getAttributeCode())) {
                    if ($this->frontendBlocksRepository->getFrontendBlock($attribute)) {
                        $recordHtml .= '<div class="form-record-row">';
                        $recordHtml .= '<strong>{{fieldLabel id="' . $attribute->getAttributeCode() . '" admin="1"}}</strong>';
                        $recordHtml .= ': ';
                        $recordHtml .= '{{fieldValue id="' . $attribute->getAttributeCode() . '" admin="1"}}';
                        $recordHtml .= '</div>';
                    }
                }
            }
            $recordHtml .= '</div>';

            $this->templateFilter->setFormRecord($formRecord);
            $templateParams['recordHtml'] = $this->templateFilter->filter($recordHtml);
        }
        return $templateParams;
    }

    protected function validateFormEditParams ($params = []) {
        $form = $this->formRecord->getForm();
        $required = $form->getData('required_record_params');

        if (!$form->getData('allow_guest_submit') || in_array('order_id', $required) || in_array('order_item_id', $required)) {
            $required[] = 'form_id';
            $required[] = 'record_id';

            return array_reduce($required, function ($carry, $item) use ($params) {
                return $carry && array_key_exists($item, $params);
            }, true);
        }

        return false;
    }
}
