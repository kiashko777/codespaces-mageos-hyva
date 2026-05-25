<?php
declare(strict_types=1);

namespace Local\Personpack\Plugin;

use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Model\Order\Item as OrderItem;

class SavePersonpackToOrderItem
{
    public function afterConvert(
        ToOrderItem $subject,
        OrderItem $orderItem,
        QuoteItem $quoteItem,
        array $data = []
    ): OrderItem {
        $personName = (string) $quoteItem->getData('personpack_person_name');
        if ($personName === '') {
            return $orderItem;
        }

        $sequence = (int) $quoteItem->getData('personpack_sequence');

        $options = $orderItem->getProductOptions() ?? [];
        $options['additional_options'] = array_merge(
            $options['additional_options'] ?? [],
            [
                [
                    'label' => 'Person',
                    'value' => $personName,
                ],
                [
                    'label' => 'Pack',
                    'value' => '#' . $sequence,
                ],
            ]
        );
        $orderItem->setProductOptions($options);

        $orderItem->setData('personpack_person_name', $personName);
        $orderItem->setData('personpack_person_id', $quoteItem->getData('personpack_person_id'));
        $orderItem->setData('personpack_sequence', $sequence);

        return $orderItem;
    }
}
