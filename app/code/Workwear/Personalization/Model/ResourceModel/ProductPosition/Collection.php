<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model\ResourceModel\ProductPosition;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Workwear\Personalization\Model\ProductPosition;
use Workwear\Personalization\Model\ResourceModel\ProductPosition as ProductPositionResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(ProductPosition::class, ProductPositionResource::class);
    }
}
