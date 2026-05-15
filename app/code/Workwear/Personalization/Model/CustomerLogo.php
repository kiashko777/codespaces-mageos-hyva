<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model;

use Magento\Framework\Model\AbstractModel;
use Workwear\Personalization\Model\ResourceModel\CustomerLogo as CustomerLogoResource;

class CustomerLogo extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(CustomerLogoResource::class);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');
        return $value !== null ? (int) $value : null;
    }

    public function getFilePath(): string
    {
        return (string) $this->getData('file_path');
    }

    public function getStatus(): int
    {
        return (int) $this->getData('status');
    }

    public function getFileHash(): string
    {
        return (string) $this->getData('file_hash');
    }

    public function getCreatedAt(): string
    {
        return (string) $this->getData('created_at');
    }
}
