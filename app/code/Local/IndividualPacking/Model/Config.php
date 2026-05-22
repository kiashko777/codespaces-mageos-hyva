<?php
declare(strict_types=1);

namespace Local\IndividualPacking\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED   = 'workwear/individual_packing/enabled';
    private const XML_PATH_FEE       = 'workwear/individual_packing/fee_per_item';
    private const XML_PATH_LABEL     = 'workwear/individual_packing/label';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getFeePerItem(): float
    {
        return (float) $this->scopeConfig->getValue(self::XML_PATH_FEE, ScopeInterface::SCOPE_STORE);
    }

    public function getLabel(): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_LABEL, ScopeInterface::SCOPE_STORE)
            ?? 'Individual Packing');
    }
}
