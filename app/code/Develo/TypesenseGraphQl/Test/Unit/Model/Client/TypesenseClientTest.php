<?php

declare(strict_types=1);

namespace Develo\TypesenseGraphQl\Test\Unit\Model\Client;

use Develo\TypesenseGraphQl\Model\Client\TypesenseClient;
use Develo\TypesenseGraphQl\Model\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Typesense\MultiSearch;

class TypesenseClientTest extends TestCase
{
    private TypesenseClient $tsClient;
    private Config|MockObject $config;

    protected function setUp(): void
    {
        $this->config   = $this->createMock(Config::class);
        $this->tsClient = new TypesenseClient($this->config);
    }

    private function injectSdkClient(object $sdk): void
    {
        $ref = new \ReflectionProperty(TypesenseClient::class, 'client');
        $ref->setAccessible(true);
        $ref->setValue($this->tsClient, $sdk);
    }

    public function testSuggestReturnsMappedProductsAndCategories(): void
    {
        $multiSearch = $this->createMock(MultiSearch::class);
        $multiSearch->method('perform')->willReturn([
            'results' => [
                [
                    'hits' => [
                        ['document' => ['id' => '1', 'name' => 'Blue Shirt', 'sku' => 'BS-001', 'url' => '/blue-shirt', 'image_url' => '/img.jpg', 'price' => 29.99, 'categories' => ['Men']]],
                    ],
                ],
                [
                    'hits' => [
                        ['document' => ['name' => 'Men', 'url' => '/men', 'breadcrumb' => ['Men']]],
                    ],
                ],
            ],
        ]);

        $sdk = new \stdClass();
        $sdk->multiSearch = $multiSearch;
        $this->injectSdkClient($sdk);
        $this->config->method('getCollectionPrefix')->willReturn('magento2');

        $result = $this->tsClient->suggest('shirt');

        $this->assertCount(1, $result['products']);
        $this->assertSame('Blue Shirt', $result['products'][0]['name']);
        $this->assertSame('BS-001', $result['products'][0]['sku']);
        $this->assertSame([], $result['terms']);
        $this->assertCount(1, $result['categories']);
        $this->assertSame('Men', $result['categories'][0]['name']);
    }

    public function testSearchReturnsMappedItemsAndFacets(): void
    {
        $rawResponse = [
            'hits' => [
                ['document' => ['id' => '1', 'name' => 'Red Hat', 'sku' => 'RH-001', 'url' => '/red-hat', 'image_url' => '/img.jpg', 'price' => 19.99, 'categories' => ['Hats']]],
            ],
            'facet_counts'   => [
                ['field_name' => 'categories', 'counts' => [['value' => 'Hats', 'count' => 4]]],
            ],
            'found'          => 1,
            'search_time_ms' => 5,
            'page'           => 1,
        ];

        $documents   = new class($rawResponse) {
            public function __construct(private array $response) {}
            public function search(array $p): array { return $this->response; }
        };
        $collection  = new class($documents) {
            public function __construct(public object $documents) {}
        };
        $collections = new class($collection) implements \ArrayAccess {
            public function __construct(private object $c) {}
            public function offsetGet(mixed $key): object { return $this->c; }
            public function offsetExists(mixed $key): bool { return true; }
            public function offsetSet(mixed $key, mixed $value): void {}
            public function offsetUnset(mixed $key): void {}
        };

        $sdk = new \stdClass();
        $sdk->collections = $collections;
        $this->injectSdkClient($sdk);
        $this->config->method('getCollectionPrefix')->willReturn('magento2');

        $result = $this->tsClient->search('hat', 1, 20, [], null);

        $this->assertCount(1, $result['items']);
        $this->assertSame('Red Hat', $result['items'][0]['name']);
        $this->assertSame(1, $result['total_count']);
        $this->assertSame(5, $result['search_time_ms']);
        $this->assertCount(1, $result['facets']);
        $this->assertSame('categories', $result['facets'][0]['name']);
        $this->assertSame(1, $result['page_info']['total_pages']);
    }
}
