<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model\Total;

use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Workwear\Personalization\Model\PersonalizationFeeCalculator;

class PersonalizationFee extends AbstractTotal
{
    protected $_code = 'personalization_fee';

    public function __construct(
        private readonly PersonalizationFeeCalculator $calculator
    ) {}

    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ): static {
        parent::collect($quote, $shippingAssignment, $total);

        if ($shippingAssignment->getShipping()->getAddress()->getAddressType() !== Quote\Address::TYPE_SHIPPING) {
            return $this;
        }

        $totalFee = $this->calculator->calculate($quote);

        if ($totalFee > 0.0) {
            $total->setTotalAmount($this->getCode(), $totalFee);
            $total->setBaseTotalAmount($this->getCode(), $totalFee);
        }

        return $this;
    }

    public function fetch(Quote $quote, Total $total): array
    {
        $amount = $total->getTotalAmount($this->getCode());
        if (!$amount) {
            return [];
        }

        return [
            'code'  => $this->getCode(),
            'title' => __('Personalization Setup Fee'),
            'value' => $amount,
        ];
    }
}
