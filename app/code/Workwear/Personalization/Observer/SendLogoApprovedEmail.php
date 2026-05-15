<?php

declare(strict_types=1);

namespace Workwear\Personalization\Observer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Workwear\Personalization\Model\CustomerLogo;

class SendLogoApprovedEmail implements ObserverInterface
{
    private const EMAIL_TEMPLATE       = 'workwear_logo_approved';
    private const CONFIG_SENDER_EMAIL  = 'trans_email/ident_general/email';
    private const CONFIG_SENDER_NAME   = 'trans_email/ident_general/name';

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {}

    public function execute(Observer $observer): void
    {
        /** @var CustomerLogo $logo */
        $logo       = $observer->getData('logo');
        $customerId = $logo->getCustomerId();

        if ($customerId === null) {
            return;
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $store    = $this->storeManager->getDefaultStoreView();
            if ($store === null) {
                $this->logger->error('Workwear logo approval email failed: no default store view configured.');
                return;
            }
            $storeId  = (int) $store->getId();

            $mediaUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            $logoUrl  = rtrim($mediaUrl, '/') . '/' . ltrim($logo->getFilePath(), '/');

            $senderEmail = (string) $this->scopeConfig->getValue(
                self::CONFIG_SENDER_EMAIL,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            $senderName = (string) $this->scopeConfig->getValue(
                self::CONFIG_SENDER_NAME,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            $customerName = trim($customer->getFirstname() . ' ' . $customer->getLastname());

            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::EMAIL_TEMPLATE)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars([
                    'customer_name' => $customerName,
                    'logo_uid'      => $logo->getFileHash(),
                    'logo_url'      => $logoUrl,
                    'store_name'    => $store->getName(),
                ])
                ->setFrom(['email' => $senderEmail, 'name' => $senderName])
                ->addTo($customer->getEmail(), $customerName)
                ->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->logger->error(
                'Workwear logo approval email failed: ' . $e->getMessage(),
                ['logo_id' => $logo->getId(), 'customer_id' => $customerId]
            );
        }
    }
}
