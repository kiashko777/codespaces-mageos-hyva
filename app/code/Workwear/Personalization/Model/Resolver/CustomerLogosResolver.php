<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Workwear\Personalization\Api\Data\CustomerLogoInterface;
use Workwear\Personalization\Model\ResourceModel\CustomerLogo\CollectionFactory;

class CustomerLogosResolver implements ResolverInterface
{
    private const STATUS_LABELS = [
        CustomerLogoInterface::STATUS_PENDING  => 'pending',
        CustomerLogoInterface::STATUS_APPROVED => 'approved',
        CustomerLogoInterface::STATUS_REJECTED => 'rejected',
    ];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
    ) {}

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        if (!$context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('Customer must be logged in to view logos.'));
        }

        $customerId = (int) $context->getUserId();
        $mediaBaseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', ['eq' => $customerId]);
        $collection->setOrder('created_at', 'DESC');

        $result = [];
        foreach ($collection as $logo) {
            $filePath = $logo->getFilePath();
            $result[] = [
                'uid'        => $logo->getFileHash(),
                'url'        => rtrim($mediaBaseUrl, '/') . '/' . ltrim($filePath, '/'),
                'filename'   => basename($filePath),
                'status'     => self::STATUS_LABELS[$logo->getStatus()] ?? 'unknown',
                'created_at' => $logo->getCreatedAt(),
            ];
        }

        return $result;
    }
}
