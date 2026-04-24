<?php

declare(strict_types=1);

namespace Develo\TypesenseGraphQl\Model\Client;

use Develo\TypesenseGraphQl\Api\TypesenseClientInterface;
use Develo\TypesenseGraphQl\Model\Config;
use Typesense\Client;

class TypesenseClient implements TypesenseClientInterface
{
    private mixed $client = null;

    public function __construct(
        private readonly Config $config
    ) {}

    public function suggest(string $query): array
    {
        $prefix = $this->config->getCollectionPrefix();

        $results = $this->getClient()->multiSearch->perform([
            'searches' => [
                [
                    'collection' => $prefix . '_products',
                    'q'          => $query,
                    'query_by'   => 'name,sku,description',
                    'per_page'   => 5,
                    'prefix'     => true,
                ],
                [
                    'collection' => $prefix . '_categories',
                    'q'          => $query,
                    'query_by'   => 'name',
                    'per_page'   => 3,
                    'prefix'     => true,
                ],
            ],
        ], []);

        return [
            'products'   => $this->mapProductHits($results['results'][0]['hits'] ?? []),
            'categories' => $this->mapCategoryHits($results['results'][1]['hits'] ?? []),
            'terms'      => [],
        ];
    }

    public function search(string $query, int $page, int $pageSize, array $filters, ?array $sort): array
    {
        $prefix = $this->config->getCollectionPrefix();

        $params = [
            'q'        => $query,
            'query_by' => 'name,sku,description',
            'per_page' => $pageSize,
            'page'     => $page,
            'facet_by' => 'categories,price',
        ];

        if ($filters !== []) {
            $params['filter_by'] = $this->buildFilterString($filters);
        }

        if ($sort !== null) {
            $params['sort_by'] = $sort['field'] . ':' . strtolower($sort['direction']);
        }

        $raw = $this->getClient()->collections[$prefix . '_products']->documents->search($params);

        return [
            'items'          => $this->mapProductHits($raw['hits'] ?? []),
            'facets'         => $this->mapFacets($raw['facet_counts'] ?? []),
            'total_count'    => (int) ($raw['found'] ?? 0),
            'search_time_ms' => (int) ($raw['search_time_ms'] ?? 0),
            'page_info'      => [
                'current_page' => $page,
                'page_size'    => $pageSize,
                'total_pages'  => $pageSize > 0 ? (int) ceil(($raw['found'] ?? 0) / $pageSize) : 0,
            ],
        ];
    }

    private function getClient(): mixed
    {
        if ($this->client === null) {
            $this->client = new Client([
                'api_key'                    => $this->config->getSearchKey(),
                'nodes'                      => [[
                    'host'     => $this->config->getHost(),
                    'port'     => $this->config->getPort(),
                    'protocol' => $this->config->getProtocol(),
                ]],
                'connection_timeout_seconds' => 2,
            ]);
        }
        return $this->client;
    }

    private function mapProductHits(array $hits): array
    {
        return array_map(static fn(array $hit): array => [
            'id'         => (int) ($hit['document']['id'] ?? 0),
            'name'       => $hit['document']['name'] ?? null,
            'sku'        => $hit['document']['sku'] ?? null,
            'url'        => $hit['document']['url'] ?? null,
            'image_url'  => $hit['document']['image_url'] ?? null,
            'price'      => isset($hit['document']['price']) ? (float) $hit['document']['price'] : null,
            'categories' => $hit['document']['categories'] ?? [],
        ], $hits);
    }

    private function mapCategoryHits(array $hits): array
    {
        return array_map(static fn(array $hit): array => [
            'name'       => $hit['document']['name'] ?? null,
            'url'        => $hit['document']['url'] ?? null,
            'breadcrumb' => $hit['document']['breadcrumb'] ?? [],
        ], $hits);
    }

    private function mapFacets(array $facetCounts): array
    {
        return array_map(static fn(array $facet): array => [
            'name'    => $facet['field_name'],
            'label'   => ucfirst(str_replace('_', ' ', $facet['field_name'])),
            'options' => array_map(static fn(array $c): array => [
                'value' => $c['value'],
                'label' => $c['value'],
                'count' => (int) $c['count'],
            ], $facet['counts'] ?? []),
        ], $facetCounts);
    }

    private function buildFilterString(array $filters): string
    {
        $parts = [];
        foreach ($filters as $filter) {
            $parts[] = match ($filter['condition_type'] ?? 'eq') {
                'gt'    => $filter['field'] . ':>' . $filter['value'],
                'lt'    => $filter['field'] . ':<' . $filter['value'],
                'gte'   => $filter['field'] . ':>=' . $filter['value'],
                'lte'   => $filter['field'] . ':<=' . $filter['value'],
                default => $filter['field'] . ':=' . $filter['value'],
            };
        }
        return implode(' && ', $parts);
    }
}
