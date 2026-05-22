<?php
declare(strict_types=1);

namespace Local\IndividualPacking\Plugin;

use Local\IndividualPacking\Model\Config;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Model\Order\Item as OrderItem;

class SaveIndividualPackingToOrderItem
{
    public function __construct(
        private readonly Config $config
    ) {}

    public function afterConvert(
        ToOrderItem $subject,
        OrderItem $orderItem,
        QuoteItem $quoteItem,
        array $data = []
    ): OrderItem {
        if (!(int) $quoteItem->getData('individual_packing_selected')) {
            return $orderItem;
        }

        $qty        = (float) $quoteItem->getQty();
        $feePerItem = $this->config->getFeePerItem();
        $totalFee   = round($qty * $feePerItem, 2);

        $options = $orderItem->getProductOptions() ?? [];
        $options['additional_options'] = array_merge(
            $options['additional_options'] ?? [],
            [
                [
                    'label' => 'Individual Packing',
                    'value' => 'Yes — each item bagged and labelled separately',
                ],
                [
                    'label' => 'Individual Packing Fee',
                    'value' => '£' . number_format($totalFee, 2)
                        . ' (£' . number_format($feePerItem, 2) . ' × ' . (int) $qty . ')',
                ],
            ]
        );
        $orderItem->setProductOptions($options);

        return $orderItem;
    }
}
