<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class CartItemPersonalizationData implements ResolverInterface
{
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): ?string
    {
        $cartItem = $value['model'] ?? null;
        if ($cartItem === null) {
            return null;
        }

        $data = $cartItem->getData('personalization_data');
        return $data !== null && $data !== '' ? (string) $data : null;
    }
}
