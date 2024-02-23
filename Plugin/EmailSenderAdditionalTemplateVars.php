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

    protected $frontendBlocksRepository;

    protected $templateFilter;

    /** @var \Alekseon\CustomFormsBuilder\Model\FormRecord $formRecord */
    protected $formRecord = null;

    public function __construct(
        \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerCollectionFactory,
        \Alekseon\CustomFormsFrontend\Model\FrontendBlocksRepository $frontendBlocksRepository,
        \Alekseon\CustomFormsFrontend\Model\Template\Filter $templateFilter

    ) {
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->frontendBlocksRepository = $frontendBlocksRepository;
        $this->templateFilter = $templateFilter;
    }

    public function afterGetTemplateParams (
        \Alekseon\CustomFormsEmailNotification\Model\Email\AbstractSender $subject,
        array $result
    ) {
        // todo : in case of asynchronous bulk email sending (cron) alekseon's logic (querying db separately for every email we're planning to send)
        // todo : leaves a lot to be desired, change it in the future
        if (!array_key_exists('customer', $result) && $this->formRecord) {
            if (($customerId = $this->formRecord->getData('customer_id')) && is_numeric($customerId)) {
                try {
                    $collection = $this->customerCollectionFactory->create();
                    $collection->addFieldToFilter(
                        'entity_id',
                        ['eq' => $customerId]
                    );

                    if ($collection->getSize()) {
                        $customerModel = $collection->getFirstItem();
                        $result['customer'] = $customerModel;
                    }
                } catch (\Throwable $e) {}
            }
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
}
