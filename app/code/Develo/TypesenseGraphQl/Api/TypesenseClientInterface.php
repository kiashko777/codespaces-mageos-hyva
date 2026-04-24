<?php

declare(strict_types=1);

namespace Develo\TypesenseGraphQl\Api;

interface TypesenseClientInterface
{
    /**
     * @return array{products: array, categories: array, terms: array}
     */
    public function suggest(string $query): array;

    /**
     * @param array<array{field: string, value: string, condition_type?: string}> $filters
     * @param array{field: string, direction: string}|null $sort
     * @return array{items: array, facets: array, total_count: int, search_time_ms: int, page_info: array}
     */
    public function search(string $query, int $page, int $pageSize, array $filters, ?array $sort): array;
}
