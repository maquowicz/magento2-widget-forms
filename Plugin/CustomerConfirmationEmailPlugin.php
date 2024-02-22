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
class CustomerConfirmationEmailPlugin
{
    protected $customerRepository;

    /** @var \Alekseon\CustomFormsBuilder\Model\FormRecord $formRecord */
    protected $formRecord = null;

    public function __construct(
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
    ) {
        $this->customerRepository = $customerRepository;
    }

    public function afterGetReceiverEmails (
        \Alekseon\CustomFormsEmailNotification\Model\Email\CustomerConfirmation $subject,
        array $result
    ) {
            try {
                if (empty($result) &&  $this->formRecord) {
                    $email = $this->formRecord->getData('customer_email');
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $result[] = $email;
                    }
                }
            } catch (\Throwable $e) {

            }
        return $result;
    }

    public function afterSetFormRecord (
        \Alekseon\CustomFormsEmailNotification\Model\Email\CustomerConfirmation $subject,
        \Alekseon\CustomFormsEmailNotification\Model\Email\CustomerConfirmation $result,
        \Alekseon\CustomFormsBuilder\Model\FormRecord $formRecord
    ) {
        $this->formRecord = $formRecord;
        return $result;
    }
}
