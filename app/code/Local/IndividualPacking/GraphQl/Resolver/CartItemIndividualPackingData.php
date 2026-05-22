<?php
declare(strict_types=1);

namespace Local\IndividualPacking\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class CartItemIndividualPackingData implements ResolverInterface
{
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): ?bool {
        $cartItem = $value['model'] ?? null;
        if ($cartItem === null) {
            return null;
        }

        return (bool) $cartItem->getData('individual_packing_selected');
    }
}
