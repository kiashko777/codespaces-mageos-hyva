<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ProductPosition extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('workwear_product_position', 'entity_id');
    }
}
