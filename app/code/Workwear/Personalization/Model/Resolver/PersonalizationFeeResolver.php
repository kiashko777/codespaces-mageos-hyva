<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;
use Workwear\Personalization\Model\PersonalizationFeeCalculator;

class PersonalizationFeeResolver implements ResolverInterface
{
    public function __construct(
        private readonly PersonalizationFeeCalculator $calculator
    ) {}

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        if (!isset($value['model']) || !$value['model'] instanceof Quote) {
            throw new GraphQlInputException(__('"model" value must be a Quote.'));
        }

        /** @var Quote $quote */
        $quote = $value['model'];

        return [
            'value' => $this->calculator->calculate($quote),
            'currency' => $quote->getQuoteCurrencyCode(),
        ];
    }
}
