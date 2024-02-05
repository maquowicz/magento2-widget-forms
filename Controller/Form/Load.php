<?php
/*
 * Copyright (c) 2011-2023 PFP Łukasz Maksimowicz
 * https://www.ipfp.pl
 * +48 790 790 543
 *
 * Need help or want to customize anything?
 * Drop us a line at our contact page: https://www.ipfp.pl/contact
 *
 * All title, including but not limited to copyrights, in and to the software product
 * and any copies thereof are owned by PFP Łukasz Maksimowicz.
 * All rights not expressly granted are reserved by PFP Łukasz Maksimowicz.
 *
 * @category Module
 * @package SD
 * @author Łukasz Maksimowicz <maxim@ipfp.pl>
 * @license https://www.ipfp.pl/licenses/magento
 */

namespace Alekseon\WidgetForms\Controller\Form;


use Alekseon\CustomFormsBuilder\Model\FormRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory as OrderItemCollectionFactory;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator as FormkeyValidator;
use Magento\Customer\Model\Session as CustomerSession;

use Magento\Framework\Exception\LocalizedException;

class Load implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    protected $formRepository;

    protected $orderCollectionFactory;

    protected $orderItemCollectionFactory;

    /** @var RequestInterface  */
    protected $request;

    /** @var ResultFactory  */
    protected $resultFactory;

    /** @var UrlInterface  */
    protected $urlBuilder;

    /** @var FormKey  */
    protected $formkey;

    /** @var FormkeyValidator  */
    protected $formkeyValidator;

    /**
     * @var CustomerSession
     */
    protected $customerSession;


    public function __construct(
        FormRepository $formRepository,
        OrderCollectionFactory $orderCollectionFactory,
        OrderItemCollectionFactory $orderItemCollectionFactory,
        RequestInterface $request,
        ResultFactory $resultFactory,
        UrlInterface $urlBuilder,
        Formkey $formkey,
        FormkeyValidator $formkeyValidator,
        CustomerSession $customerSession

    ) {

        $this->formRepository = $formRepository;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderItemCollectionFactory = $orderItemCollectionFactory;
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->urlBuilder = $urlBuilder;
        $this->formkey = $formkey;
        $this->formkeyValidator = $formkeyValidator;
        $this->customerSession = $customerSession;

    }

    public function execute() {

        $result = [
            'error' => true,
            'is_logged_in' => false,
            'allow_guest_submit' => null,
            'guest_submit_invalidated' => false,
            'missing_params' => [],
            'validated' => false,
            'messages' => []
        ];

        try {
            if (!$this->formkeyValidator->validate($this->request)) {
                throw new LocalizedException(__());
            }

            $result['is_logged_in'] = $this->customerSession->isLoggedIn();

            if (!($formMode = $this->request->getParam('form_mode')) || !in_array($formMode, ['new', 'edit'])) {
                throw new LocalizedException(__());
            }

            if (!($formId = $this->request->getParam('form_id'))) {
                throw new LocalizedException(__());
            }

            try {
                $form = $this->formRepository->getById($formId);
            } catch (\Throwable $e) {
                throw new LocalizedException(__());
            }

            $result['allow_guest_submit'] = (bool) $form->getData('allow_guest_submit');


            $required = $form->getData('required_record_params') ?? [];
            $supplied = json_decode($this->request->getParam('form_params'), true);

            $validate = true;

            if (in_array('order_id', $required) ) {
                if (!$result['is_logged_in']) {
                    $result['guest_submit_invalidated'] = true;
                    $validate = false;
                } else if (!array_key_exists('order_id', $supplied) || !is_numeric($supplied['order_id'])) {
                    $result['missing_params'][] = 'order_id';
                    $validate = false;
                }
            }

            if (in_array('order_item_id', $required)) {
                if (!$result['is_logged_in']) {
                    $result['guest_submit_invalidated'] = true;
                    $validate = false;
                } else if (!array_key_exists('order_item_id', $supplied) || !is_numeric($supplied['order_item_id'])) {
                    $result['missing_params'][] = 'order_item_id';
                    $validate = false;
                }
            }







        } catch (\Throwable $e) {
            $message = 'Something went wrong';
            if ($e instanceof LocalizedException && strlen(trim($e->getMessage()))) {
                $message = $e->getMessage();
            }
            $result['messages'][] = ['type' => 'error', 'text' => $message];
        }

        if (!$this->customerSession->isLoggedIn()) {
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath('customer/account/login');

            return $resultRedirect;
        }
    }

    public function matchParams ($required, $supplied) {

    }
}
