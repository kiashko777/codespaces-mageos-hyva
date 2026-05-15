<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Workwear\Personalization\Model\ResourceModel\ProductPosition\CollectionFactory;

class AvailablePositions implements ResolverInterface
{
    public function __construct(
        private readonly CollectionFactory $positionCollectionFactory
    ) {}

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        $productId = (int) ($value['model']->getId() ?? 0);
        if ($productId === 0) {
            return [];
        }

        $collection = $this->positionCollectionFactory->create();
        $collection->addFieldToFilter('product_id', ['eq' => $productId]);

        $result = [];
        foreach ($collection->getColumnValues('position_code') as $code) {
            $result[] = ['position_code' => $code];
        }

        return $result;
    }
}
