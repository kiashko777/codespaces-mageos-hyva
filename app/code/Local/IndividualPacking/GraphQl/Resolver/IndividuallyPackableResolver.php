<?php
declare(strict_types=1);

namespace Local\IndividualPacking\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class IndividuallyPackableResolver implements ResolverInterface
{
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): ?string {
        $product = $value['model'] ?? null;
        if ($product === null) {
            return null;
        }

        $optionId = $product->getData('individually_packable');
        if ($optionId === null || $optionId === '') {
            return null;
        }

        // getData returns option_id (int); getAttributeText returns the label
        // which equals the machine-readable code set in AddIndividuallyPackableAttribute
        return $product->getAttributeText('individually_packable') ?: null;
    }
}
