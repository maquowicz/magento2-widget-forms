<?php
/**
 * Copyright © Alekseon sp. z o.o.
 * http://www.alekseon.com/
 */
declare(strict_types=1);

namespace Alekseon\WidgetForms\Model\Attribute\Source;

/**
 * Class TextFormAttributes
 * @package Alekseon\CustomFormsBuilder\Model\Attribute\Source
 */
class OrderStatuses extends \Alekseon\AlekseonEav\Model\Attribute\Source\AbstractSource
{
    private $orderStatusCollection = null;

    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollectionFactory
    ) {
        $this->orderStatusCollection = $orderStatusCollectionFactory->create();
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        $result = [];
        foreach ($this->orderStatusCollection as $item) {
            $result[$item['status']] = $item['label'];
        }

        return $result;
    }
}
