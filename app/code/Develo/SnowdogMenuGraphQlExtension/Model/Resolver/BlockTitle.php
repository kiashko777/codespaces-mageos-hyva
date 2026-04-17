<?php

declare(strict_types=1);

namespace Develo\SnowdogMenuGraphQlExtension\Model\Resolver;

use Magento\Cms\Api\Data\BlockInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class BlockTitle implements ResolverInterface
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

        if ($block === null) {
            return null;
        }

        $title = $block[BlockInterface::TITLE] ?? null;

        return $title === null ? null : (string) $title;
    }
}
