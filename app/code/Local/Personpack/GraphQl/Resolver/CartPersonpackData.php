<?php
declare(strict_types=1);

namespace Local\Personpack\GraphQl\Resolver;

use Local\Personpack\Model\Config;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class CartPersonpackData implements ResolverInterface
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
    ): bool|float|int|null {
        $cart = $value['model'] ?? null;
        if ($cart === null) {
            return null;
        }

        return match ($field->getName()) {
            'is_personpack'           => (bool) $cart->getData('is_personpack'),
            'personpack_people_count' => (int) $cart->getData('personpack_people_count'),
            'personpack_fee'          => $this->calculateFee($cart),
            default                   => null,
        };
    }

    private function calculateFee(\Magento\Quote\Model\Quote $cart): float
    {
        if (!(int) $cart->getData('is_personpack')) {
            return 0.0;
        }

        $count = (int) $cart->getData('personpack_people_count');

        return round($this->config->getFeePerPerson() * $count, 2);
    }
}
