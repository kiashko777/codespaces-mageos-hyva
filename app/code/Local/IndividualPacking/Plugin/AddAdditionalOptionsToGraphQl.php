<?php
namespace Local\IndividualPacking\Plugin;

class AddAdditionalOptionsToGraphQl
{
    public function afterGetOrderItemById(
        \Magento\SalesGraphQl\Model\OrderItem\DataProvider $subject,
        array $result
    ): array {
        file_put_contents('/tmp/dataprovider_debug.txt',
            'CALLED' . "\n" .
            'result keys: ' . implode(', ', array_keys($result)) . "\n" .
            'entered_options: ' . json_encode($result['entered_options'] ?? []) . "\n\n",
            FILE_APPEND
        );
        return $result;
    }
}
