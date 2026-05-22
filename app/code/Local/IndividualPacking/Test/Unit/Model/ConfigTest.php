<?php
declare(strict_types=1);

namespace Local\IndividualPacking\Test\Unit\Model;

use Local\IndividualPacking\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private Config $config;
    private ScopeConfigInterface|MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    public function testIsEnabledReturnsTrueWhenFlagSet(): void
    {
        $this->scopeConfig
            ->method('isSetFlag')
            ->with('workwear/individual_packing/enabled', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        self::assertTrue($this->config->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenFlagNotSet(): void
    {
        $this->scopeConfig
            ->method('isSetFlag')
            ->with('workwear/individual_packing/enabled', ScopeInterface::SCOPE_STORE)
            ->willReturn(false);

        self::assertFalse($this->config->isEnabled());
    }

    public function testGetFeePerItemReturnsCastFloat(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with('workwear/individual_packing/fee_per_item', ScopeInterface::SCOPE_STORE)
            ->willReturn('0.49');

        self::assertSame(0.49, $this->config->getFeePerItem());
    }

    public function testGetLabelReturnsConfiguredLabel(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with('workwear/individual_packing/label', ScopeInterface::SCOPE_STORE)
            ->willReturn('Individual Packing');

        self::assertSame('Individual Packing', $this->config->getLabel());
    }

    public function testGetLabelReturnsDefaultWhenNull(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with('workwear/individual_packing/label', ScopeInterface::SCOPE_STORE)
            ->willReturn(null);

        self::assertSame('Individual Packing', $this->config->getLabel());
    }
}
