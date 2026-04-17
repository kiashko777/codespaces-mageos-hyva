<?php

declare(strict_types=1);

namespace Develo\SnowdogMenuGraphQlExtension\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Snowdog\Menu\Api\Data\NodeInterface;

class BlockIdentifier implements ResolverInterface
{
    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): ?string {
        $identifier = $value[NodeInterface::CONTENT] ?? null;

        return $identifier === null || $identifier === '' ? null : (string) $identifier;
    }
}
