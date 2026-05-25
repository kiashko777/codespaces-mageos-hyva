<?php
declare(strict_types=1);

namespace Local\Personpack\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CopyPersonpackToOrder implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getData('order');
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $observer->getData('quote');

        if (!$order || !$quote) {
            return;
        }

        if (!(int) $quote->getData('is_personpack')) {
            return;
        }

        $order->setData('is_personpack', 1);
        $order->setData('personpack_people_count', (int) $quote->getData('personpack_people_count'));
    }
}
