<?php

declare(strict_types=1);

namespace Develo\TypesenseGraphQl\Model\Resolver;

use Develo\TypesenseGraphQl\Api\TypesenseClientInterface;
use Develo\TypesenseGraphQl\Model\Config;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class Search implements ResolverInterface
{
    public function __construct(
        private readonly TypesenseClientInterface $client,
        private readonly Config $config
    ) {}

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        $queryText = trim((string) ($args['query'] ?? ''));
        if ($queryText === '') {
            throw new GraphQlInputException(__('query cannot be empty'));
        }

        if (!$this->config->isConfigured()) {
            throw new GraphQlInputException(__('Typesense is not configured'));
        }

        $page     = max(1, (int) ($args['page'] ?? 1));
        $pageSize = max(1, (int) ($args['pageSize'] ?? 20));
        $filters  = $args['filters'] ?? [];
        $sort     = $args['sort'] ?? null;

        try {
            return $this->client->search($queryText, $page, $pageSize, $filters ?: [], $sort);
        } catch (\Exception $e) {
            throw new GraphQlNoSuchEntityException(__('Search temporarily unavailable'));
        }
    }
}
