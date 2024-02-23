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
    protected $customerSession;

    public function __construct(
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->customerSession = $customerSession;
    }


    public function execute(\Magento\Framework\Event\Observer $observer) {
        if ($this->customerSession) {
            // DVCR : Clear pending forms session data to force reload on customer dashboard routes
            $this->customerSession->unsetData('alekseon_form_customer_pending_items');
        }
        return $observer;
    }
}
