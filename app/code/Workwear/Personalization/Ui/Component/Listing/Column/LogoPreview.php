<?php

declare(strict_types=1);

namespace Workwear\Personalization\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class LogoPreview extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        foreach ($dataSource['data']['items'] as &$item) {
            $filePath = $item['file_path'] ?? '';
            if ($filePath) {
                $url = $this->escaper->escapeUrl($baseUrl . $filePath);
                $item[$this->getData('name')] = sprintf(
                    '<a href="%s" target="_blank" title="View full size">'
                    . '<img src="%s" style="max-width:80px;max-height:80px;object-fit:contain;" alt="Logo"/>'
                    . '</a>',
                    $url,
                    $url
                );
            } else {
                $item[$this->getData('name')] = '—';
            }
        }

        return $dataSource;
    }
}
