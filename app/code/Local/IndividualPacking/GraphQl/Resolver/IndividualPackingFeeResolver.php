<?php
declare(strict_types=1);

namespace Local\IndividualPacking\GraphQl\Resolver;

use Local\IndividualPacking\Model\Config;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class IndividualPackingFeeResolver implements ResolverInterface
{
    public function __construct(
        private readonly Config $config
    ) {}

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): float|string|null {
        $cart = $value['model'] ?? null;
        if ($cart === null) {
            return null;
        }

        $fee = 0.0;
        foreach ($cart->getAllVisibleItems() as $item) {
            if ((int) $item->getData('individual_packing_selected') === 1) {
                $fee += $this->config->getFeePerItem() * (float) $item->getQty();
            }
        }
        $fee = round($fee, 2);

        if ($field->getName() === 'individual_packing_fee_formatted') {
            return '£' . number_format($fee, 2);
        }

        return $fee;
    }
}
