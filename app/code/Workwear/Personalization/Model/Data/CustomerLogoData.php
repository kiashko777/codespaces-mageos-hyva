<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model\Data;

use Workwear\Personalization\Api\Data\CustomerLogoInterface;

class CustomerLogoData implements CustomerLogoInterface
{
    private string $logoUid  = '';
    private string $filePath = '';
    private int $status      = self::STATUS_PENDING;
    private string $createdAt = '';

    public function getLogoUid(): string
    {
        return $this->logoUid;
    }

    public function setLogoUid(string $logoUid): CustomerLogoInterface
    {
        $this->logoUid = $logoUid;
        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): CustomerLogoInterface
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): CustomerLogoInterface
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(string $createdAt): CustomerLogoInterface
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
