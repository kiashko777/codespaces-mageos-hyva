<?php
declare(strict_types=1);

namespace Local\Personpack\GraphQl\Resolver;

use Local\Personpack\Model\PersonpackCalculator;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;

class CartPersonpackData implements ResolverInterface
{
    public function __construct(
        private readonly PersonpackCalculator $personpackCalculator
    ) {}

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): bool|float|int|null {
        /** @var Quote|null $cart */
        $cart = $value['model'] ?? null;
        if ($cart === null) {
            return null;
        }

        $isPersonpack = (bool) $cart->getData('is_personpack');

        return match ($field->getName()) {
            'is_personpack'           => $isPersonpack,
            'personpack_people_count' => $this->personpackCalculator->countPeople($cart),
            'personpack_fee'          => $isPersonpack ? $this->personpackCalculator->calculateFee($cart) : 0.0,
            default                   => null,
        };
    }
}
