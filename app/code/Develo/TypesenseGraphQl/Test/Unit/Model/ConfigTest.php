<?php

declare(strict_types=1);

namespace Develo\TypesenseGraphQl\Test\Unit\Model;

use Develo\TypesenseGraphQl\Model\Config;
use Magento\Framework\App\DeploymentConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private Config $config;
    private DeploymentConfig|MockObject $deploymentConfig;

    protected function setUp(): void
    {
        $this->deploymentConfig = $this->createMock(DeploymentConfig::class);
        $this->config = new Config($this->deploymentConfig);
    }

    public function testGetHostReturnsConfiguredValue(): void
    {
        $this->deploymentConfig->method('get')
            ->with('typesense/host', '')
            ->willReturn('xxx.a1.typesense.net');

        $this->assertSame('xxx.a1.typesense.net', $this->config->getHost());
    }

    public function testGetPortDefaultsTo443(): void
    {
        $this->deploymentConfig->method('get')
            ->with('typesense/port', '443')
            ->willReturn('443');

        $this->assertSame('443', $this->config->getPort());
    }

    public function testGetSearchKeyReturnsConfiguredValue(): void
    {
        $this->deploymentConfig->method('get')
            ->with('typesense/search_key', '')
            ->willReturn('abc123');

        $this->assertSame('abc123', $this->config->getSearchKey());
    }

    public function testGetCollectionPrefixReturnsConfiguredValue(): void
    {
        $this->deploymentConfig->method('get')
            ->with('typesense/collection_prefix', 'magento2')
            ->willReturn('mystore');

        $this->assertSame('mystore', $this->config->getCollectionPrefix());
    }

    public function testIsConfiguredReturnsTrueWhenHostAndKeyPresent(): void
    {
        $this->deploymentConfig->method('get')
            ->willReturnMap([
                ['typesense/host', '', 'xxx.a1.typesense.net'],
                ['typesense/search_key', '', 'abc123'],
            ]);

        $this->assertTrue($this->config->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenHostMissing(): void
    {
        $this->deploymentConfig->method('get')
            ->willReturnMap([
                ['typesense/host', '', ''],
                ['typesense/search_key', '', 'abc123'],
            ]);

        $this->assertFalse($this->config->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenKeyMissing(): void
    {
        $this->deploymentConfig->method('get')
            ->willReturnMap([
                ['typesense/host', '', 'xxx.a1.typesense.net'],
                ['typesense/search_key', '', ''],
            ]);

        $this->assertFalse($this->config->isConfigured());
    }
}
