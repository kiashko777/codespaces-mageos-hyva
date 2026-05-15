<?php

declare(strict_types=1);

namespace Workwear\Personalization\Api\Data;

interface LogoUploadResultInterface
{
    /**
     * @return string
     */
    public function getLogoUid(): string;

    /**
     * @param string $logoUid
     * @return \Workwear\Personalization\Api\Data\LogoUploadResultInterface
     */
    public function setLogoUid(string $logoUid): LogoUploadResultInterface;
}
