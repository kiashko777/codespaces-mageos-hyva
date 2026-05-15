<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model\Data;

use Workwear\Personalization\Api\Data\LogoUploadResultInterface;

class LogoUploadResult implements LogoUploadResultInterface
{
    private string $logoUid = '';

    public function getLogoUid(): string
    {
        return $this->logoUid;
    }

    public function setLogoUid(string $logoUid): LogoUploadResultInterface
    {
        $this->logoUid = $logoUid;
        return $this;
    }
}
