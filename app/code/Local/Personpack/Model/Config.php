<?php
declare(strict_types=1);

namespace Local\Personpack\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED       = 'workwear/personpack/enabled';
    private const XML_PATH_FEE           = 'workwear/personpack/fee_per_person';
    private const XML_PATH_LABEL         = 'workwear/personpack/label';
    private const XML_PATH_PERSON_LABEL  = 'workwear/personpack/person_label';
    private const XML_PATH_PACK_LABEL    = 'workwear/personpack/pack_label';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getFeePerPerson(): float
    {
        return (float) $this->scopeConfig->getValue(self::XML_PATH_FEE, ScopeInterface::SCOPE_STORE);
    }

    public function getLabel(): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_LABEL, ScopeInterface::SCOPE_STORE)
            ?: 'Personpack Fee');
    }

    public function getPersonLabel(): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_PERSON_LABEL, ScopeInterface::SCOPE_STORE)
            ?: 'Person');
    }

    public function getPackLabel(): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_PACK_LABEL, ScopeInterface::SCOPE_STORE)
            ?: 'Pack');
    }
}
