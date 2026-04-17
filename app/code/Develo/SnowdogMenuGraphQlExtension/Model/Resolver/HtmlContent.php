<?php

declare(strict_types=1);

namespace Develo\SnowdogMenuGraphQlExtension\Model\Resolver;

use Magento\Cms\Api\Data\BlockInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class HtmlContent implements ResolverInterface
{
    /**
     * @var CmsBlockDataFetcher
     */
    private $blockDataFetcher;

    public function __construct(CmsBlockDataFetcher $blockDataFetcher)
    {
        $this->blockDataFetcher = $blockDataFetcher;
    }

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
        $block = $this->blockDataFetcher->fetch($value, $context);

        return $block[BlockInterface::CONTENT] ?? null;
    }
}
