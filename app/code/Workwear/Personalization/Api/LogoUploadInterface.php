<?php

declare(strict_types=1);

namespace Workwear\Personalization\Api;

interface LogoUploadInterface
{
    /**
     * Upload a logo file via multipart/form-data (field name: logo).
     * Returns logo_uid (= SHA-256 file hash) usable in GraphQL cart mutations.
     *
     * @return \Workwear\Personalization\Api\Data\LogoUploadResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function upload(): Data\LogoUploadResultInterface;

    /**
     * List all logos belonging to the authenticated customer.
     *
     * @return \Workwear\Personalization\Api\Data\CustomerLogoInterface[]
     */
    public function getCustomerLogos(): array;
}
