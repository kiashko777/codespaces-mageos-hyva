<?php
declare(strict_types=1);

namespace Local\Personpack\Model;

use Magento\Quote\Model\Quote;

class PersonpackCalculator
{
    public function __construct(
        private readonly Config $config
    ) {}

    /**
     * Count distinct people present on the cart's personpack items.
     */
    public function countPeople(Quote $quote): int
    {
        $personIds = [];

        foreach ($quote->getAllVisibleItems() as $item) {
            $personId = (string) $item->getData('personpack_person_id');
            if ($personId !== '') {
                $personIds[$personId] = true;
            }
        }

        return count($personIds);
    }

    /**
     * Fee = configured per-person fee × number of distinct people.
     */
    public function calculateFee(Quote $quote): float
    {
        return round($this->config->getFeePerPerson() * $this->countPeople($quote), 2);
    }
}
