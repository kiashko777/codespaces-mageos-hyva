<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model\ResourceModel\CustomerLogo;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Workwear\Personalization\Model\CustomerLogo as CustomerLogoModel;
use Workwear\Personalization\Model\ResourceModel\CustomerLogo as CustomerLogoResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(CustomerLogoModel::class, CustomerLogoResource::class);
    }
}
