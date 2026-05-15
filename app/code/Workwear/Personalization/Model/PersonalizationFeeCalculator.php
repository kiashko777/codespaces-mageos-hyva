<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Workwear\Personalization\Api\Data\CustomerLogoInterface;
use Workwear\Personalization\Model\ResourceModel\CustomerLogo as CustomerLogoResource;

class PersonalizationFeeCalculator
{
    private const CONFIG_LOGO_FEE = 'workwear/personalization/logo_fee';
    private const CONFIG_TEXT_FEE = 'workwear/personalization/text_fee';
    public const CONTENT_TYPE_LOGO = 'LOGO';
    public const CONTENT_TYPE_TEXT = 'TEXT';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CustomerLogoFactory $customerLogoFactory,
        private readonly CustomerLogoResource $customerLogoResource
    ) {}

    public function calculate(Quote $quote): float
    {
        $processedHashes = [];
        $totalFee = 0.0;

        foreach ($quote->getAllVisibleItems() as $item) {
            $json = $item->getData('personalization_data');
            if (!$json) {
                continue;
            }

            try {
                $personalizations = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (!is_array($personalizations)) {
                continue;
            }

            foreach ($personalizations as $p) {
                $contentType = (string) ($p['content_type'] ?? '');

                if ($contentType === self::CONTENT_TYPE_LOGO) {
                    $totalFee += $this->calcLogoFee($p, $processedHashes);
                } elseif ($contentType === self::CONTENT_TYPE_TEXT) {
                    $totalFee += $this->calcTextFee($p, $processedHashes);
                }
            }
        }

        return $totalFee;
    }

    private function calcLogoFee(array $p, array &$processedHashes): float
    {
        $logoUid = (string) ($p['logo_uid'] ?? '');
        if ($logoUid === '') {
            return 0.0;
        }

        $logo = $this->customerLogoFactory->create();
        $this->customerLogoResource->loadByFileHash($logo, $logoUid);

        if (!$logo->getId()) {
            return 0.0;
        }

        if ((int) $logo->getStatus() === CustomerLogoInterface::STATUS_APPROVED) {
            return 0.0;
        }

        $hash = (string) $logo->getFileHash();

        if (in_array($hash, $processedHashes, true)) {
            return 0.0;
        }

        $processedHashes[] = $hash;

        return (float) $this->scopeConfig->getValue(self::CONFIG_LOGO_FEE, ScopeInterface::SCOPE_STORE);
    }

    private function calcTextFee(array $p, array &$processedHashes): float
    {
        $textLines = $p['text_lines'] ?? [];
        if (empty($textLines)) {
            return 0.0;
        }

        $textHash = hash('sha256', implode('|', (array) $textLines));

        if (in_array($textHash, $processedHashes, true)) {
            return 0.0;
        }

        $processedHashes[] = $textHash;

        return (float) $this->scopeConfig->getValue(self::CONFIG_TEXT_FEE, ScopeInterface::SCOPE_STORE);
    }
}
