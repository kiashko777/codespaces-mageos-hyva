<?php
namespace Local\SalesGraphQlFix\Model\Formatter;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Stdlib\DateTime;

class Order extends \Magento\SalesGraphQl\Model\Formatter\Order
{
    public function format(OrderInterface $orderModel): array
    {
        $data = parent::format($orderModel);
        $reflection = new \ReflectionClass(\Magento\SalesGraphQl\Model\Formatter\Order::class);
        $prop = $reflection->getProperty('timezone');
        $prop->setAccessible(true);
        $timezone = $prop->getValue($this);
        $createdAt = $orderModel->getCreatedAt();
        $utcDate = new \DateTime($createdAt, new \DateTimeZone('UTC'));
        $data['order_date'] = $timezone->date($utcDate)
            ->format(DateTime::DATETIME_PHP_FORMAT);
        return $data;
    }
}
