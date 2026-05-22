<?php
declare(strict_types=1);

namespace Local\IndividualPacking\Model\Quote\Total;

use Local\IndividualPacking\Model\Config;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;

class IndividualPackingFee extends AbstractTotal
{
    protected $_code = 'individual_packing_fee';

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

        // Suppress the parent's internal reset (setTotalAmount($code, 0)) so we
        // fully control when setTotalAmount is called on the $total object.
        $this->_canSetAddressAmount = false;
        parent::collect($quote, $shippingAssignment, $total);
        $this->_canSetAddressAmount = true;

        $totalFee = 0.0;
        foreach ($quote->getAllVisibleItems() as $item) {
            if ((int) $item->getData('individual_packing_selected') === 1) {
                $totalFee += $this->config->getFeePerItem() * (float) $item->getQty();
            }
        }

        $totalFee = round($totalFee, 2);

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
            'title' => __($this->config->getLabel()),
            'value' => $amount,
        ];
    }
}
