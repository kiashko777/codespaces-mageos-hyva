<?php

declare(strict_types=1);

namespace Develo\ElasticSuiteGraphQl\Test\Unit\Model\Resolver;

use ArrayIterator;
use Develo\ElasticSuiteGraphQl\Model\Resolver\Suggest;
use Magento\Catalog\Model\Product;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Search\Model\Autocomplete\ItemInterface;
use Magento\Search\Model\Query;
use Magento\Search\Model\QueryFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smile\ElasticsuiteCatalog\Helper\Autocomplete as AutocompleteHelper;
use Smile\ElasticsuiteCatalog\Model\Autocomplete\Category\DataProvider as CategoryDataProvider;
use Smile\ElasticsuiteCatalog\Model\Autocomplete\Product\Collection\Provider as ProductCollectionProvider;
use Smile\ElasticsuiteCatalog\Model\ResourceModel\Product\Fulltext\Collection as ProductCollection;
use Smile\ElasticsuiteCore\Api\Search\ContextInterface as SearchContext;
use Smile\ElasticsuiteCore\Model\Autocomplete\Terms\DataProvider as TermDataProvider;

class SuggestTest extends TestCase
{
    private Suggest $resolver;

    private QueryFactory|MockObject $queryFactory;

    private SearchContext|MockObject $searchContext;

    private ProductCollectionProvider|MockObject $productCollectionProvider;

    private CategoryDataProvider|MockObject $categoryDataProvider;

    private TermDataProvider|MockObject $termDataProvider;

    private AutocompleteHelper|MockObject $autocompleteHelper;

    protected function setUp(): void
    {
        $this->queryFactory = $this->createMock(QueryFactory::class);
        $this->searchContext = $this->createMock(SearchContext::class);
        $this->productCollectionProvider = $this->createMock(ProductCollectionProvider::class);
        $this->categoryDataProvider = $this->createMock(CategoryDataProvider::class);
        $this->termDataProvider = $this->createMock(TermDataProvider::class);
        $this->autocompleteHelper = $this->createMock(AutocompleteHelper::class);

        $this->resolver = new Suggest(
            $this->queryFactory,
            $this->searchContext,
            $this->productCollectionProvider,
            $this->categoryDataProvider,
            $this->termDataProvider,
            $this->autocompleteHelper,
        );
    }

    public function testResolveThrowsOnEmptyQuery(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('query cannot be empty');

        $this->resolver->resolve(
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            ['query' => '   ']
        );
    }

    public function testResolveSetsQueryTextAndReturnsGroupedResults(): void
    {
        $mockQuery = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->addMethods(['setQueryText'])
            ->getMock();
        $mockQuery->expects($this->once())->method('setQueryText')->with('bag');
        $this->queryFactory->expects($this->once())->method('get')->willReturn($mockQuery);
        $this->searchContext->expects($this->once())->method('setCurrentSearchQuery')->with($mockQuery);

        $this->autocompleteHelper->method('isEnabled')->with('product')->willReturn(true);

        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->addMethods(['getDocumentSource'])
            ->onlyMethods(['getProductUrl', 'getData'])
            ->getMock();
        $product->method('getDocumentSource')->willReturn([
            'name' => 'Yoga Bag',
            'sku'  => 'YB001',
        ]);
        $product->method('getProductUrl')->willReturn('https://example.com/yoga-bag.html');
        $product->method('getData')->with('final_price')->willReturn('39.99');

        $collection = $this->createMock(ProductCollection::class);
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('addPriceData')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new ArrayIterator([$product]));
        $this->productCollectionProvider->method('getProductCollection')->willReturn($collection);

        $categoryItem = $this->createMock(ItemInterface::class);
        $categoryItem->method('toArray')->willReturn([
            'title'      => 'Bags',
            'url'        => 'https://example.com/bags.html',
            'breadcrumb' => ['Gear'],
        ]);
        $this->categoryDataProvider->method('getItems')->willReturn([$categoryItem]);

        $termItem = $this->createMock(ItemInterface::class);
        $termItem->method('toArray')->willReturn([
            'title'       => 'bag',
            'num_results' => '42',
        ]);
        $this->termDataProvider->method('getItems')->willReturn([$termItem]);

        $result = $this->resolver->resolve(
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            ['query' => 'bag']
        );

        $this->assertSame('Yoga Bag', $result['products'][0]['name']);
        $this->assertSame('https://example.com/yoga-bag.html', $result['products'][0]['url']);
        $this->assertSame('YB001', $result['products'][0]['sku']);
        $this->assertSame(39.99, $result['products'][0]['price']);

        $this->assertSame('Bags', $result['categories'][0]['name']);
        $this->assertSame('https://example.com/bags.html', $result['categories'][0]['url']);
        $this->assertSame(['Gear'], $result['categories'][0]['breadcrumb']);

        $this->assertSame('bag', $result['terms'][0]['query_text']);
        $this->assertSame(42, $result['terms'][0]['num_results']);
    }

    public function testResolveReturnsEmptyProductsWhenAutocompleteDisabled(): void
    {
        $mockQuery = $this->createMock(Query::class);
        $this->queryFactory->method('get')->willReturn($mockQuery);
        $this->autocompleteHelper->method('isEnabled')->with('product')->willReturn(false);

        $this->productCollectionProvider->expects($this->never())->method('getProductCollection');

        $this->categoryDataProvider->method('getItems')->willReturn([]);
        $this->termDataProvider->method('getItems')->willReturn([]);

        $result = $this->resolver->resolve(
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            ['query' => 'bag']
        );

        $this->assertSame([], $result['products']);
    }

    public function testResolveHandlesEmptyProviderResults(): void
    {
        $mockQuery = $this->createMock(Query::class);
        $this->queryFactory->method('get')->willReturn($mockQuery);
        $this->autocompleteHelper->method('isEnabled')->with('product')->willReturn(true);

        $collection = $this->createMock(ProductCollection::class);
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('addPriceData')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new ArrayIterator([]));
        $this->productCollectionProvider->method('getProductCollection')->willReturn($collection);

        $this->categoryDataProvider->method('getItems')->willReturn([]);
        $this->termDataProvider->method('getItems')->willReturn([]);

        $result = $this->resolver->resolve(
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            ['query' => 'zzznomatch']
        );

        $this->assertSame([], $result['products']);
        $this->assertSame([], $result['categories']);
        $this->assertSame([], $result['terms']);
    }
}
