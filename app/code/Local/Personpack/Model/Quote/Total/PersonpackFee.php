<?php
declare(strict_types=1);

namespace Local\Personpack\Model\Quote\Total;

use Local\Personpack\Model\Config;
use Local\Personpack\Model\PersonpackCalculator;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;

class PersonpackFee extends AbstractTotal
{
    protected $_code = 'personpack_fee';

    public function __construct(
        private readonly Config $config,
        private readonly PersonpackCalculator $personpackCalculator
    ) {}

    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ): static {
        // Reset any previously-collected amount so a disabled/cleared personpack cart drops the fee.
        parent::collect($quote, $shippingAssignment, $total);

        if ($shippingAssignment->getShipping()->getAddress()->getAddressType() !== Quote\Address::TYPE_SHIPPING) {
            return $this;
        }

        if (!$this->config->isEnabled() || !(int) $quote->getData('is_personpack')) {
            return $this;
        }

        $fee = $this->personpackCalculator->calculateFee($quote);

        if ($fee > 0.0) {
            $total->setTotalAmount($this->getCode(), $fee);
            $total->setBaseTotalAmount($this->getCode(), $fee);
        }

        return $this;
    }

    public function fetch(Quote $quote, Total $total): array
    {
        if (!(int) $quote->getData('is_personpack')) {
            return [];
        }

        $amount = $total->getTotalAmount($this->getCode());
        if (!$amount) {
            return [];
        }

        return [
            'code'  => $this->getCode(),
            'title' => __($this->config->getLabel()),
            'value' => $amount,
        ];
    }
}
