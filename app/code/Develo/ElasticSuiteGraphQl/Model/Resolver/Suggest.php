<?php

declare(strict_types=1);

namespace Develo\ElasticSuiteGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Search\Model\QueryFactory;
use Smile\ElasticsuiteCatalog\Helper\Autocomplete as AutocompleteHelper;
use Smile\ElasticsuiteCatalog\Model\Autocomplete\Product\Collection\Provider as ProductCollectionProvider;
use Smile\ElasticsuiteCatalog\Model\Autocomplete\Category\DataProvider as CategoryDataProvider;
use Smile\ElasticsuiteCore\Api\Search\ContextInterface as SearchContext;
use Smile\ElasticsuiteCore\Model\Autocomplete\Terms\DataProvider as TermDataProvider;

class Suggest implements ResolverInterface
{
    private const PRODUCT_PAGE_SIZE = 5;

    public function __construct(
        private readonly QueryFactory $queryFactory,
        private readonly SearchContext $searchContext,
        private readonly ProductCollectionProvider $productCollectionProvider,
        private readonly CategoryDataProvider $categoryDataProvider,
        private readonly TermDataProvider $termDataProvider,
        private readonly AutocompleteHelper $autocompleteHelper,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        $queryText = trim((string) ($args['query'] ?? ''));
        if ($queryText === '') {
            throw new GraphQlInputException(__('query cannot be empty'));
        }

        $query = $this->queryFactory->get();
        $query->setQueryText($queryText);
        $this->searchContext->setCurrentSearchQuery($query);

        return [
            'products'   => $this->resolveProducts(),
            'categories' => $this->resolveCategories(),
            'terms'      => $this->resolveTerms(),
        ];
    }

    private function resolveProducts(): array
    {
        if (!$this->autocompleteHelper->isEnabled('product')) {
            return [];
        }

        $collection = $this->productCollectionProvider->getProductCollection();
        $collection->setPageSize(self::PRODUCT_PAGE_SIZE);
        $collection->addPriceData();

        $results = [];
        foreach ($collection as $product) {
            $src = $product->getDocumentSource() ?? [];

            $name = is_array($src['name'] ?? null) ? current($src['name']) : ($src['name'] ?? null);
            $sku  = is_array($src['sku'] ?? null) ? current($src['sku']) : ($src['sku'] ?? null);

            $results[] = [
                'name'      => $name,
                'url'       => $product->getProductUrl(),
                'sku'       => $sku,
                'image_url' => null,
                'price'     => $this->extractNumericPrice($product->getData('final_price')),
            ];
        }
        return $results;
    }

    private function resolveCategories(): array
    {
        $results = [];
        foreach ($this->categoryDataProvider->getItems() as $item) {
            $data = $item->toArray();
            $results[] = [
                'name'       => $data['title'] ?? null,
                'url'        => $data['url'] ?? null,
                'breadcrumb' => $data['breadcrumb'] ?? [],
            ];
        }
        return $results;
    }

    private function resolveTerms(): array
    {
        $results = [];
        foreach ($this->termDataProvider->getItems() as $item) {
            $data = $item->toArray();
            $results[] = [
                'query_text'  => $data['title'] ?? null,
                'num_results' => isset($data['num_results']) ? (int) $data['num_results'] : null,
            ];
        }
        return $results;
    }

    /**
     * Extract a numeric price from a raw numeric or HTML-rendered price value.
     */
    private function extractNumericPrice(mixed $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        $stripped = preg_replace('/[^0-9.]/', '', strip_tags((string) $raw));
        return $stripped !== '' ? (float) $stripped : null;
    }
}
