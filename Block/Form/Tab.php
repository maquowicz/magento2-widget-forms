<?php
/**
 * Copyright © Alekseon sp. z o.o.
 * http://www.alekseon.com/
 *
 */
declare(strict_types=1);

namespace Alekseon\WidgetForms\Block\Form;

use Alekseon\CustomFormsBuilder\Model\Form;
use Alekseon\CustomFormsBuilder\Model\FormTab;

/**
 * @method Tab setForm(Form $formTab)
 * @method Form getForm()
 * @method Tab setTab(FormTab $formTab)
 * @method FormTab getTab()
 */
class Tab extends \Magento\Framework\View\Element\Template
{
    protected $_template = "Alekseon_WidgetForms::form/tab.phtml";

    public function getSubmitButtonHtml()
    {
        return $this->getLayout()->createBlock(
            \Alekseon\WidgetForms\Block\Form\Action\Submit::class,
            'form_' . $this->getForm()->getId() . '_action_submit_' . $this->getTab()->getId(),
            [
                'data' => [
                    'button_label' => $this->getSubmitButtonLabel(),
                    'form_id' => 'alekseon-widget-form-' . $this->getForm()->getId(),
                    'tab_id' => $this->getTab()->getTabSequenceNumber()
                ],
            ]
        )->toHtml();
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getNextButtonHtml()
    {
        return $this->getLayout()->createBlock(
            \Alekseon\WidgetForms\Block\Form\Action\Next::class,
            'form_' . $this->getForm()->getId() . '_action_next_' . $this->getTab()->getId(),
            [
                'data' => [
                    'button_label' => $this->getNextButtonLabel(),
                    'form_id' => 'alekseon-widget-form-' . $this->getForm()->getId(),
                    'tab_id' => $this->getTab()->getTabSequenceNumber()
                ],
            ]
        )->toHtml();
    }

    public function getPreviousButtonHtml()
    {
        return $this->getLayout()->createBlock(
            \Alekseon\WidgetForms\Block\Form\Action\Previous::class,
            'form_' . $this->getForm()->getId() . '_action_previous_' . $this->getTab()->getId(),
            [
                'data' => [
                    'button_label' => $this->getPreviousButtonLabel(),
                    'form_id' => 'alekseon-widget-form-' . $this->getForm()->getId(),
                    'tab_id' => $this->getTab()->getTabSequenceNumber()
                ],
            ]
        )->toHtml();
    }



    /**
     * @return \Magento\Framework\Phrase
     */
    public function getSubmitButtonLabel()
    {
        $form = $this->getForm();
        if ($form && $form->getSubmitButtonLabel()) {
            return $form->getSubmitButtonLabel();
        }

        return __('Submit');
    }

    public function getNextButtonLabel()
    {
        return __('Next');
    }

    public function getPreviousButtonLabel()
    {
        return __('Previous');
    }

}
