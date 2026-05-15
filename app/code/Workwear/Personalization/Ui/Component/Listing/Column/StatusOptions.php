<?php

declare(strict_types=1);

namespace Workwear\Personalization\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;

class StatusOptions implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 0, 'label' => __('Pending')],
            ['value' => 1, 'label' => __('Approved')],
            ['value' => 2, 'label' => __('Rejected')],
        ];
    }
}
