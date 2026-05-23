<?php
namespace Local\IndividualPacking\Model\OrderItem;

use Magento\Sales\Api\Data\OrderItemInterface;

class OptionsProcessor extends \Magento\SalesGraphQl\Model\OrderItem\OptionsProcessor
{
    public function getItemOptions(OrderItemInterface $orderItem): array
    {
        $result = parent::getItemOptions($orderItem);
        
        $productOptions = $orderItem->getProductOptions();
        $additionalOptions = $productOptions['additional_options'] ?? [];

        if (!empty($additionalOptions)) {
            $entered = $result['entered_options'] ?? [];
            foreach ($additionalOptions as $option) {
                $entered[] = [
                    'label' => $option['label'],
                    'value' => $option['value'],
                ];
            }
            $result['entered_options'] = $entered;
        }

        return $result;
    }
}
