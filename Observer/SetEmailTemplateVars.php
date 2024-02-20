<?php
namespace Alekseon\WidgetForms\Observer;

use Magento\Framework\Event\ObserverInterface;
use Alekseon\WidgetForms\Helper\Data as DataHelper;


class SetEmailTemplateVars implements ObserverInterface
{
    protected $dataHelper;

    protected $layout;

    protected $request;

    public function __construct(
        \Alekseon\WidgetForms\Helper\Data $dataHelper,
        \Magento\Framework\View\LayoutInterface $layout,
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->dataHelper = $dataHelper;
        $this->layout = $layout;
        $this->request = $request;
    }
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        /** @var \Magento\Framework\DataObject $transport */
        $transport = $observer->getEvent()->getTransport();
        /** @var \Magento\Sales\Model\Order $order */
        $order = $transport->getOrder();

        if (null !== $order) {
            $result = $this->dataHelper->getRelatedFormsData (
                $order->getCustomerId(),
                $order->getId()
            );

            $hasRelatedForms = false;
            $hasMissingFormLinks = false;
            $hasFormLinks = false;

            if (is_array($result) && count($result)) {
                $hasRelatedForms = true;
                foreach ($result as &$item) {

                    if (!empty($item['alekseon_form_url_key'])) {
                        $params = [
                            'customer_id' => $item['customer_id'],
                            'order_id' => $item['order_id'],
                            'order_item_id' => $item['item_id']
                        ];

                        $url = $this->dataHelper->getFormUrlByKey(
                            $item['alekseon_form_url_key'],
                            $params
                        );
                        $item['form_url'] = $url;
                        $hasFormLinks = true;
                    } else {
                        $item['form_url'] = null;
                        $hasMissingFormLinks = true;
                    }
                }
            }

            $transport->setData('has_related_forms', $hasRelatedForms);
            $transport->setData('has_missing_form_links', $hasMissingFormLinks);
            $transport->setData('form_links_html', '');

            if (is_array($result) && count($result)) {
                $block = $this->layout->createBlock(
                    \Magento\Framework\View\Element\Template::class,
                    'related_forms_order_email'
                );

                $block->setTemplate('Alekseon_WidgetForms::email/order/parts/related_forms.phtml');
                $block->setData('has_form_links', $hasFormLinks);
                $block->setData('has_missing_form_links', $hasMissingFormLinks);

                $block->setData('items', $result);
                $html = trim((string)$block->toHtml());

                $transport->setData('form_links_html', $html);
            }


        }

        return $observer;
    }
}
