<?php

declare(strict_types=1);

namespace Develo\SnowdogMenuGraphQlExtension\Plugin\GraphQl\Node;

use Magento\Cms\Model\Block as BlockModel;
use Snowdog\Menu\Api\Data\NodeInterface;
use Snowdog\Menu\Model\GraphQl\Resolver\Node\Identity as NodeIdentity;

/**
 * Appends CMS block cache tags to the snowdogMenuNodes query cache entry so
 * edits to a referenced block invalidate the cached menu response.
 */
class IdentityPlugin
{
    private const NODE_TYPE_CMS_BLOCK = 'cms_block';

    /**
     * @param NodeIdentity $subject
     * @param string[] $result
     * @param array $resolvedData
     * @return string[]
     */
    public function afterGetIdentities(
        NodeIdentity $subject,
        array $result,
        array $resolvedData
    ): array {
        $blockTags = $this->collectBlockTags($resolvedData['items'] ?? []);

        if (empty($blockTags)) {
            return $result;
        }

        return array_values(array_unique(array_merge($result, $blockTags)));
    }

    /**
     * @param array $items
     * @return string[]
     */
    private function collectBlockTags(array $items): array
    {
        $tags = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (($item[NodeInterface::TYPE] ?? null) !== self::NODE_TYPE_CMS_BLOCK) {
                continue;
            }
            $blockIdentifier = $item[NodeInterface::CONTENT] ?? null;
            if (empty($blockIdentifier)) {
                continue;
            }
            $tags[] = BlockModel::CACHE_TAG . '_' . $blockIdentifier;
        }

        if (!empty($tags)) {
            array_unshift($tags, BlockModel::CACHE_TAG);
        }

        return $tags;
    }
}
