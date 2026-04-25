<?php

declare(strict_types=1);

namespace Develo\TypesenseGraphQl\Test\Unit\Model\Resolver;

use Develo\TypesenseGraphQl\Api\TypesenseClientInterface;
use Develo\TypesenseGraphQl\Model\Config;
use Develo\TypesenseGraphQl\Model\Resolver\Search;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SearchTest extends TestCase
{
    private Search $resolver;
    private TypesenseClientInterface|MockObject $client;
    private Config|MockObject $config;

    protected function setUp(): void
    {
        $this->client = $this->createMock(TypesenseClientInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->resolver = new Search($this->client, $this->config);
    }

    public function testThrowsOnEmptyQuery(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('query cannot be empty');

        $this->resolver->resolve(
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            ['query' => '', 'page' => 1, 'pageSize' => 20, 'filters' => null, 'sort' => null]
        );
    }

    public function testThrowsWhenNotConfigured(): void
    {
        $this->config->method('isConfigured')->willReturn(false);
        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('Typesense is not configured');

        $this->resolver->resolve(
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            ['query' => 'shirt', 'page' => 1, 'pageSize' => 20, 'filters' => null, 'sort' => null]
        );
    }

    public function testReturnsSearchResults(): void
    {
        $this->config->method('isConfigured')->willReturn(true);
        $this->client->method('search')
            ->with('shirt', 1, 20, [], null)
            ->willReturn([
                'items'          => [['id' => 1, 'name' => 'Red Shirt', 'sku' => 'RS-001', 'url' => '/red-shirt', 'image_url' => '/img.jpg', 'price' => 19.99, 'categories' => ['Men']]],
                'facets'         => [['name' => 'categories', 'label' => 'Categories', 'options' => [['value' => 'Men', 'label' => 'Men', 'count' => 3]]]],
                'total_count'    => 1,
                'search_time_ms' => 4,
                'page_info'      => ['current_page' => 1, 'page_size' => 20, 'total_pages' => 1],
            ]);

        $result = $this->resolver->resolve(
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            ['query' => 'shirt', 'page' => 1, 'pageSize' => 20, 'filters' => null, 'sort' => null]
        );

        $this->assertSame(1, $result['total_count']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('Red Shirt', $result['items'][0]['name']);
        $this->assertCount(1, $result['facets']);
    }

    public function testClientExceptionBecomesGraphQlError(): void
    {
        $this->config->method('isConfigured')->willReturn(true);
        $this->client->method('search')->willThrowException(new \RuntimeException('timeout'));

        $this->expectException(GraphQlNoSuchEntityException::class);
        $this->expectExceptionMessage('Search temporarily unavailable');

        $this->resolver->resolve(
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            ['query' => 'shirt', 'page' => 1, 'pageSize' => 20, 'filters' => null, 'sort' => null]
        );
    }
}
