<?php
declare(strict_types=1);

namespace Develo\UrlRewriteGraphQlFix\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\ScopeInterface;
use Magento\UrlRewriteGraphQl\Model\Resolver\Route;

/**
 * Retries route resolution with the configured URL suffix appended when
 * the incoming URL has no suffix (e.g. Typesense url_key "cora-parachute-pant"
 * instead of "cora-parachute-pant.html").
 */
class RouteUrlSuffixPlugin
{
    private const XML_PATH_PRODUCT_URL_SUFFIX = 'catalog/seo/product_url_suffix';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function aroundResolve(
        Route $subject,
        callable $proceed,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): mixed {
        $result = $proceed($field, $context, $info, $value, $args);

        if ($result !== null) {
            return $result;
        }

        $url = $args['url'] ?? '';
        if ($url === '') {
            return null;
        }

        $storeId = (int) $context->getExtensionAttributes()->getStore()->getId();
        $suffix = (string) $this->scopeConfig->getValue(
            self::XML_PATH_PRODUCT_URL_SUFFIX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($suffix === '' || str_ends_with($url, $suffix)) {
            return null;
        }

        $args['url'] = $url . $suffix;
        return $proceed($field, $context, $info, $value, $args);
    }
}
