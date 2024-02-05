<?php
/**
 * Copyright © Alekseon sp. z o.o.
 * http://www.alekseon.com/
 */
declare(strict_types=1);

namespace Alekseon\WidgetForms\Model\Attribute\Source;

use Alekseon\CustomFormsBuilder\Model\Form;

/**
 * Class TextFormAttributes
 * @package Alekseon\CustomFormsBuilder\Model\Attribute\Source
 */
class Parameters extends \Alekseon\AlekseonEav\Model\Attribute\Source\AbstractSource
{
    /**
     * @var \Magento\Framework\Registry
     */
    private $coreRegistry;

    /**
     * TextFormAttributes constructor.
     * @param \Magento\Framework\Registry $coreRegistry
     */
    public function __construct(
        \Magento\Framework\Registry $coreRegistry
    )
    {
        $this->coreRegistry = $coreRegistry;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        $options = [
            'order_id' => 'Order ID',
            'order_item_id' => 'Order Item ID'
        ];

        return $options;
    }
}
