<?php

declare(strict_types=1);

namespace Develo\SnowdogMenuGraphQlExtension\Model\Resolver;

use Magento\CmsGraphQl\Model\Resolver\DataProvider\Block as BlockDataProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GraphQl\Model\Query\ContextInterface;
use Snowdog\Menu\Api\Data\NodeInterface;

/**
 * Shared fetcher for CMS block data on Snowdog menu nodes.
 *
 * Loads and in-memory caches the rendered block array per (identifier, storeId)
 * so that sibling resolvers on the same node (html_content, block_title) don't
 * each issue their own repository call.
 */
class CmsBlockDataFetcher
{
    /**
     * @var BlockDataProvider
     */
    private $blockDataProvider;

    /**
     * @var array<string, array|null>
     */
    private $cache = [];

    public function __construct(BlockDataProvider $blockDataProvider)
    {
        $this->blockDataProvider = $blockDataProvider;
    }

    /**
     * Fetch block data for the given Snowdog node value array.
     *
     * Returns null when the node has no block identifier stored, or the
     * referenced block does not exist / is inactive for the current store.
     *
     * @param array|null $value Resolved node data (keys from Snowdog NodeInterface)
     * @param ContextInterface|mixed $context GraphQL resolver context
     * @return array|null
     */
    public function fetch(?array $value, $context): ?array
    {
        if (empty($value[NodeInterface::CONTENT])) {
            return null;
        }

        $identifier = (string) $value[NodeInterface::CONTENT];
        $storeId = (int) $context->getExtensionAttributes()->getStore()->getId();
        $cacheKey = $storeId . ':' . $identifier;

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        try {
            $this->cache[$cacheKey] = $this->blockDataProvider->getBlockByIdentifier($identifier, $storeId);
        } catch (NoSuchEntityException $e) {
            $this->cache[$cacheKey] = null;
        }

        return $this->cache[$cacheKey];
    }
}
