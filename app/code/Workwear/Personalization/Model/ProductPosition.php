<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model;

use Magento\Framework\Model\AbstractModel;
use Workwear\Personalization\Model\ResourceModel\ProductPosition as ProductPositionResource;

class ProductPosition extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ProductPositionResource::class);
    }
}
