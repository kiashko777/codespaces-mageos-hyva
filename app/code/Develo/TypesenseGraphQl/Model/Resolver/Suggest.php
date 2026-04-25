<?php

declare(strict_types=1);

namespace Develo\TypesenseGraphQl\Model\Resolver;

use Develo\TypesenseGraphQl\Api\TypesenseClientInterface;
use Develo\TypesenseGraphQl\Model\Config;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class Suggest implements ResolverInterface
{
    public function __construct(
        private readonly TypesenseClientInterface $client,
        private readonly Config $config
    ) {}

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        $queryText = trim((string) ($args['query'] ?? ''));
        if ($queryText === '') {
            throw new GraphQlInputException(__('query cannot be empty'));
        }

        if (!$this->config->isConfigured()) {
            throw new GraphQlInputException(__('Typesense is not configured'));
        }

        try {
            return $this->client->suggest($queryText);
        } catch (\Exception $e) {
            throw new GraphQlNoSuchEntityException(__('Search temporarily unavailable'));
        }
    }
}
