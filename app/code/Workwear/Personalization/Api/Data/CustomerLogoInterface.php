<?php

declare(strict_types=1);

namespace Workwear\Personalization\Api\Data;

interface CustomerLogoInterface
{
    public const STATUS_PENDING  = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    /**
     * @return string
     */
    public function getLogoUid(): string;

    /**
     * @param string $logoUid
     * @return \Workwear\Personalization\Api\Data\CustomerLogoInterface
     */
    public function setLogoUid(string $logoUid): CustomerLogoInterface;

    /**
     * @return string
     */
    public function getFilePath(): string;

    /**
     * @param string $filePath
     * @return \Workwear\Personalization\Api\Data\CustomerLogoInterface
     */
    public function setFilePath(string $filePath): CustomerLogoInterface;

    /**
     * @return int
     */
    public function getStatus(): int;

    /**
     * @param int $status
     * @return \Workwear\Personalization\Api\Data\CustomerLogoInterface
     */
    public function setStatus(int $status): CustomerLogoInterface;

    /**
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * @param string $createdAt
     * @return \Workwear\Personalization\Api\Data\CustomerLogoInterface
     */
    public function setCreatedAt(string $createdAt): CustomerLogoInterface;
}
