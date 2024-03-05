<?php
/**
 * Copyright (c) 2019. PFP Łukasz Maksimowicz
 * https://www.ipfp.pl
 * +48 790 790 543
 *
 * Need help? Drop us a line at our contact page:
 * https://www.ipfp.pl/contact
 *
 * Want to customize or need help with your store?
 * Phone: +48 790 790 543
 * Email: info@ipfp.pl
 *
 * @category Module
 * @package Prescription
 * @author Łukasz Maksimowicz <maxim@ipfp.pl>
 * @license https://www.ipfp.pl/licenses/magento
 */

namespace Alekseon\WidgetForms\Controller\Customer\Account;

use Magento\Customer\Controller\AccountInterface;

class Index extends \Magento\Customer\Controller\AbstractAccount implements AccountInterface
{
    protected $_pageFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory
    ) {
        $this->_pageFactory = $pageFactory;
        return parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->_pageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Completed Forms List'));
        return $resultPage;
    }
}
