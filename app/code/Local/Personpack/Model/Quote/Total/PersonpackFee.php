<?php
declare(strict_types=1);

namespace Local\Personpack\Model\Quote\Total;

use Local\Personpack\Model\Config;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;

class PersonpackFee extends AbstractTotal
{
    protected $_code = 'personpack_fee';

    public function __construct(
        private readonly Config $config
    ) {}

    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ): static {
        if ($shippingAssignment->getShipping()->getAddress()->getAddressType() !== Quote\Address::TYPE_SHIPPING) {
            return $this;
        }

        if (!$this->config->isEnabled()) {
            return $this;
        }

        if (!(int) $quote->getData('is_personpack')) {
            return $this;
        }

        // Suppress AbstractTotal's zero-reset so we fully control setTotalAmount
        $this->_canSetAddressAmount = false;
        parent::collect($quote, $shippingAssignment, $total);
        $this->_canSetAddressAmount = true;

        $peopleCount = (int) $quote->getData('personpack_people_count');
        $fee         = round($this->config->getFeePerPerson() * $peopleCount, 2);

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
