<?php

declare(strict_types=1);

namespace Develo\SnowdogMenuGraphQlExtension\Plugin\GraphQl\Menu;

use Magento\Cms\Model\Block as BlockModel;
use Magento\Store\Model\StoreManagerInterface;
use Snowdog\Menu\Api\Data\NodeInterface;
use Snowdog\Menu\Model\GraphQl\Resolver\DataProvider\Node as NodeDataProvider;
use Snowdog\Menu\Model\GraphQl\Resolver\Menu\Identity as MenuIdentity;
use Snowdog\Menu\Model\Menu as MenuModel;

/**
 * Appends CMS block cache tags to the snowdogMenus query cache entry.
 *
 * The base Menu\Identity only tags by menu identifier, which means edits to a
 * referenced CMS block do not invalidate the cached snowdogMenus response.
 *
 * The Node\DataProvider has an in-memory cache keyed by menu identifier, so
 * when the query also requests nested nodes (the common case) this plugin
 * reuses the already-loaded data. When only menu-level fields are requested,
 * it performs a single additional node lookup per menu — acceptable cost for
 * correctness.
 */
class IdentityPlugin
{
    private const NODE_TYPE_CMS_BLOCK = 'cms_block';

    /**
     * @var NodeDataProvider
     */
    private $nodeDataProvider;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        NodeDataProvider $nodeDataProvider,
        StoreManagerInterface $storeManager
    ) {
        $this->nodeDataProvider = $nodeDataProvider;
        $this->storeManager = $storeManager;
    }

    /**
     * @param MenuIdentity $subject
     * @param string[] $result
     * @param array $resolvedData
     * @return string[]
     */
    public function afterGetIdentities(
        MenuIdentity $subject,
        array $result,
        array $resolvedData
    ): array {
        $items = $resolvedData['items'] ?? [];

        if (empty($items)) {
            return $result;
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable $e) {
            return $result;
        }

        $blockTags = $this->collectBlockTagsForMenus($items, $storeId);

        if (empty($blockTags)) {
            return $result;
        }

        return array_values(array_unique(array_merge($result, $blockTags)));
    }

    /**
     * @param array $menuItems
     * @param int $storeId
     * @return string[]
     */
    private function collectBlockTagsForMenus(array $menuItems, int $storeId): array
    {
        $tags = [];

        foreach ($menuItems as $menu) {
            if (!is_array($menu)) {
                continue;
            }
            $menuIdentifier = $menu[MenuModel::IDENTIFIER] ?? null;
            if (empty($menuIdentifier)) {
                continue;
            }

            try {
                $nodes = $this->nodeDataProvider->getNodesByMenuIdentifier(
                    (string) $menuIdentifier,
                    $storeId
                );
            } catch (\Throwable $e) {
                continue;
            }

            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                if (($node[NodeInterface::TYPE] ?? null) !== self::NODE_TYPE_CMS_BLOCK) {
                    continue;
                }
                $blockIdentifier = $node[NodeInterface::CONTENT] ?? null;
                if (empty($blockIdentifier)) {
                    continue;
                }
                $tags[] = BlockModel::CACHE_TAG . '_' . $blockIdentifier;
            }
        }

        if (!empty($tags)) {
            array_unshift($tags, BlockModel::CACHE_TAG);
        }

        return $tags;
    }
}
