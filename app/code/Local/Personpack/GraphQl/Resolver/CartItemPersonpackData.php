<?php
declare(strict_types=1);

namespace Local\Personpack\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class CartItemPersonpackData implements ResolverInterface
{
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): string|int|null {
        $cartItem = $value['model'] ?? null;
        if ($cartItem === null) {
            return null;
        }

        return match ($field->getName()) {
            'personpack_person_id'   => $cartItem->getData('personpack_person_id') ?: null,
            'personpack_person_name' => $cartItem->getData('personpack_person_name') ?: null,
            'personpack_sequence'    => $cartItem->getData('personpack_sequence') !== null
                ? (int) $cartItem->getData('personpack_sequence')
                : null,
            default => null,
        };
    }
}
