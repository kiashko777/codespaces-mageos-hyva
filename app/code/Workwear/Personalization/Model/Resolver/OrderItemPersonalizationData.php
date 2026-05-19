<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class OrderItemPersonalizationData implements ResolverInterface
{
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): ?string
    {
        $orderItem = $value['model'] ?? null;
        if ($orderItem === null) {
            return null;
        }

        $data = $orderItem->getData('personalization_data');
        return $data !== null && $data !== '' ? (string) $data : null;
    }
}
