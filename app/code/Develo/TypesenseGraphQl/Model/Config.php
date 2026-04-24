<?php

declare(strict_types=1);

namespace Develo\TypesenseGraphQl\Model;

use Magento\Framework\App\DeploymentConfig;

class Config
{
    public function __construct(
        private readonly DeploymentConfig $deploymentConfig
    ) {}

    public function getHost(): string
    {
        return (string) $this->deploymentConfig->get('typesense/host', '');
    }

    public function getPort(): string
    {
        return (string) $this->deploymentConfig->get('typesense/port', '443');
    }

    public function getProtocol(): string
    {
        return (string) $this->deploymentConfig->get('typesense/protocol', 'https');
    }

    public function getSearchKey(): string
    {
        return (string) $this->deploymentConfig->get('typesense/search_key', '');
    }

    public function getCollectionPrefix(): string
    {
        return (string) $this->deploymentConfig->get('typesense/collection_prefix', 'magento2');
    }

    public function isConfigured(): bool
    {
        return $this->getHost() !== '' && $this->getSearchKey() !== '';
    }
}
