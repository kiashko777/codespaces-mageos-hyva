<?php
namespace Local\OrderParentSku\Plugin;

use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;

class OrderItemFormatterPlugin
{
    private OrderItemRepositoryInterface $orderItemRepository;

    public function __construct(OrderItemRepositoryInterface $orderItemRepository)
    {
        $this->orderItemRepository = $orderItemRepository;
    }

    public function afterFormat(
        \Magento\SalesGraphQl\Model\Formatter\Order\Item $subject,
        array $result,
        OrderItemInterface $orderItem
    ): array {
        $parentItemId = $orderItem->getParentItemId();
        if ($parentItemId) {
            try {
                $parentItem = $this->orderItemRepository->get($parentItemId);
                $result['parent_sku'] = $parentItem->getSku();
            } catch (\Exception $e) {
                $result['parent_sku'] = $orderItem->getSku();
            }
        } else {
            $result['parent_sku'] = $orderItem->getSku();
        }
        return $result;
    }
}
