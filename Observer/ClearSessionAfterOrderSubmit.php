<?php
/**
 * Webkul Software.
 *
 * @category  Webkul
 * @package   Webkul_AdvancedBookingSystem
 * @author    Webkul
 * @copyright Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */
namespace Alekseon\WidgetForms\Observer;


use Magento\Framework\Event\ObserverInterface;

class ClearSessionAfterOrderSubmit implements ObserverInterface
{
    public function __construct(
    ) {

    }

    /**
     * Execute
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        return $observer;
    }


}
