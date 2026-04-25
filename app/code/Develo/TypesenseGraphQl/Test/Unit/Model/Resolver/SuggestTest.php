<?php

declare(strict_types=1);

namespace Develo\TypesenseGraphQl\Test\Unit\Model\Resolver;

use Develo\TypesenseGraphQl\Api\TypesenseClientInterface;
use Develo\TypesenseGraphQl\Model\Config;
use Develo\TypesenseGraphQl\Model\Resolver\Suggest;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SuggestTest extends TestCase
{
    private Suggest $resolver;
    private TypesenseClientInterface|MockObject $client;
    private Config|MockObject $config;

    protected function setUp(): void
    {
        $this->client = $this->createMock(TypesenseClientInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->resolver = new Suggest($this->client, $this->config);
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
            ['query' => '   ']
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
            ['query' => 'shirt']
        );
    }

    public function testReturnsGroupedResults(): void
    {
        $this->config->method('isConfigured')->willReturn(true);
        $this->client->method('suggest')->with('shirt')->willReturn([
            'products'   => [['name' => 'Blue Shirt', 'sku' => 'BS-001', 'url' => '/blue-shirt', 'image_url' => '/img.jpg', 'price' => 29.99]],
            'categories' => [['name' => 'Men', 'url' => '/men', 'breadcrumb' => ['Men']]],
            'terms'      => [],
        ]);

        $result = $this->resolver->resolve(
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            ['query' => 'shirt']
        );

        $this->assertCount(1, $result['products']);
        $this->assertSame('Blue Shirt', $result['products'][0]['name']);
        $this->assertCount(1, $result['categories']);
        $this->assertSame([], $result['terms']);
    }

    public function testClientExceptionBecomesGraphQlError(): void
    {
        $this->config->method('isConfigured')->willReturn(true);
        $this->client->method('suggest')->willThrowException(new \RuntimeException('connection refused'));

        $this->expectException(GraphQlNoSuchEntityException::class);
        $this->expectExceptionMessage('Search temporarily unavailable');

        $this->resolver->resolve(
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            ['query' => 'shirt']
        );
    }
}
