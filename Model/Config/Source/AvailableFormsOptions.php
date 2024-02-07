<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Alekseon\WidgetForms\Model\Config\Source;

class AvailableFormsOptions extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{

    protected $formCollectionFactory;

    protected $forms = null;


    public function __construct(
        \Alekseon\CustomFormsBuilder\Model\ResourceModel\Form\CollectionFactory $formCollectionFactory
    ) {
        $this->formCollectionFactory = $formCollectionFactory;
    }
    /**
     * Get all options
     *
     * @return array
     */
    public function getAllOptions()
    {
        if (null === $this->forms) {
            $collection = $this->formCollectionFactory->create();
            $collection->addAttributeToSelect('title');
            $collection->addAttributeToFilter('can_use_for_widget', true);

            $result = [];
            /** @var \Alekseon\CustomFormsBuilder\Model\Form $item */
            foreach ($collection as $item) {
                $result[] = ['label' => $item->getTitle(), 'value' => $item->getId()];
            }
            $this->forms = $result;
        }
        return $this->forms;
    }

    /**
     * Get a text for option value
     *
     * @param string|integer $value
     * @return string|bool
     */
    public function getOptionText($value)
    {
        foreach ($this->getAllOptions() as $option) {
            if ($option['value'] == $value) {
                return $option['label'];
            }
        }
        return false;
    }
}
