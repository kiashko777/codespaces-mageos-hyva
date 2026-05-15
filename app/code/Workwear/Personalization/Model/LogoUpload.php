<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Store\Model\ScopeInterface;
use Workwear\Personalization\Api\Data\CustomerLogoInterface;
use Workwear\Personalization\Api\Data\CustomerLogoInterfaceFactory;
use Workwear\Personalization\Api\Data\LogoUploadResultInterface;
use Workwear\Personalization\Api\Data\LogoUploadResultInterfaceFactory;
use Workwear\Personalization\Api\LogoUploadInterface;
use Workwear\Personalization\Model\CustomerLogoFactory;
use Workwear\Personalization\Model\ResourceModel\CustomerLogo as CustomerLogoResource;
use Workwear\Personalization\Model\ResourceModel\CustomerLogo\CollectionFactory;

class LogoUpload implements LogoUploadInterface
{
    private const CONFIG_MAX_FILE_SIZE = 'workwear/personalization/max_file_size';
    private const CONFIG_ALLOWED_MIME  = 'workwear/personalization/allowed_mime';
    private const MEDIA_SUBDIR         = 'workwear/logos';

    private const UPLOAD_ERRORS = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by server extension.',
    ];

    public function __construct(
        private readonly HttpRequest $request,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Filesystem $filesystem,
        private readonly UserContextInterface $userContext,
        private readonly CustomerLogoFactory $customerLogoFactory,
        private readonly CustomerLogoResource $customerLogoResource,
        private readonly CollectionFactory $collectionFactory,
        private readonly LogoUploadResultInterfaceFactory $resultFactory,
        private readonly CustomerLogoInterfaceFactory $customerLogoDataFactory
    ) {}

    public function upload(): LogoUploadResultInterface
    {
        $customerId = $this->getCustomerId();
        $file       = $this->getUploadedFile();

        $this->validateFile($file);

        $fileHash = hash_file('sha256', $file['tmp_name']);

        // Dedup per customer: same customer uploading same file again
        $existing = $this->customerLogoFactory->create();
        $this->customerLogoResource->loadByFileHashAndCustomer($existing, $fileHash, $customerId);
        if ($existing->getId()) {
            return $this->buildResult($fileHash);
        }

        // Check if file already exists on disk (another customer uploaded same file)
        $globalExisting = $this->customerLogoFactory->create();
        $this->customerLogoResource->loadByFileHash($globalExisting, $fileHash);

        if ($globalExisting->getId()) {
            $filePath = $globalExisting->getFilePath();
        } else {
            $filePath = $this->saveFile($file, $fileHash, $customerId);
        }

        $logo = $this->customerLogoFactory->create();
        $logo->setData([
            'customer_id' => $customerId,
            'file_path'   => $filePath,
            'status'      => CustomerLogoInterface::STATUS_PENDING,
            'file_hash'   => $fileHash,
        ]);

        $connection = $this->customerLogoResource->getConnection();
        $connection->beginTransaction();
        try {
            $this->customerLogoResource->save($logo);
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            // Only unlink file we just created (not a reused file from another customer)
            if (!$globalExisting->getId()) {
                $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
                $mediaDir->delete($filePath);
            }
            throw new LocalizedException(__('Could not save logo record: %1', $e->getMessage()), $e);
        }

        return $this->buildResult($fileHash);
    }

    public function getCustomerLogos(): array
    {
        $customerId = $this->getCustomerId();
        if ($customerId === null) {
            return [];
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', ['eq' => $customerId]);
        $collection->setOrder('created_at', 'DESC');

        $result = [];
        foreach ($collection as $logo) {
            /** @var CustomerLogo $logo */
            $dto = $this->customerLogoDataFactory->create();
            $dto->setLogoUid($logo->getFileHash())
                ->setFilePath($logo->getFilePath())
                ->setStatus($logo->getStatus())
                ->setCreatedAt($logo->getCreatedAt());
            $result[] = $dto;
        }

        return $result;
    }

    private function getUploadedFile(): array
    {
        $file = $this->request->getFiles('logo');

        if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new LocalizedException(__('No file uploaded. Send multipart/form-data with field name "logo".'));
        }

        $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($errorCode !== UPLOAD_ERR_OK) {
            $message = self::UPLOAD_ERRORS[$errorCode] ?? sprintf('Upload error code: %d', $errorCode);
            throw new LocalizedException(__($message));
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new LocalizedException(__('Invalid upload — file not found in server temp directory.'));
        }

        return $file;
    }

    private function validateFile(array $file): void
    {
        $maxSize = (int) $this->scopeConfig->getValue(self::CONFIG_MAX_FILE_SIZE, ScopeInterface::SCOPE_STORE);
        if ($file['size'] > $maxSize) {
            throw new LocalizedException(__(
                'File size %1 bytes exceeds allowed maximum of %2 bytes.',
                $file['size'],
                $maxSize
            ));
        }

        $allowedMimes = array_map(
            'trim',
            explode(',', (string) $this->scopeConfig->getValue(self::CONFIG_ALLOWED_MIME, ScopeInterface::SCOPE_STORE))
        );

        $finfo        = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($file['tmp_name']);

        // finfo may return text/xml or text/html for SVG files — normalise by inspecting content
        if (in_array($detectedMime, ['text/xml', 'text/html', 'application/xml'], true)) {
            $header = file_get_contents($file['tmp_name'], false, null, 0, 512);
            if ($header !== false && stripos($header, '<svg') !== false) {
                $detectedMime = 'image/svg+xml';
            }
        }

        if (!in_array($detectedMime, $allowedMimes, true)) {
            throw new LocalizedException(__(
                'File type "%1" is not allowed. Allowed types: %2',
                $detectedMime,
                implode(', ', $allowedMimes)
            ));
        }
    }

    private function saveFile(array $file, string $fileHash, ?int $customerId): string
    {
        $subDir   = self::MEDIA_SUBDIR . '/' . ($customerId ?? 'guest');
        $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $mediaDir->create($subDir);

        $extension    = $this->resolveExtension($file['name']);
        $safeFilename = $fileHash . '.' . $extension;
        $relativePath = $subDir . '/' . $safeFilename;
        $absolutePath = $mediaDir->getAbsolutePath($relativePath);

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            throw new LocalizedException(__('Could not save uploaded file. Check media directory permissions.'));
        }

        if ($extension === 'svg') {
            $this->sanitizeSvg($absolutePath);
        }

        return $relativePath;
    }

    /**
     * Strip XSS vectors from SVG: script elements, foreignObject, on* event attributes.
     */
    private function sanitizeSvg(string $absolutePath): void
    {
        $prev = libxml_use_internal_errors(true);
        $dom  = new \DOMDocument();
        $dom->load($absolutePath, LIBXML_NONET | LIBXML_NOENT);
        libxml_use_internal_errors($prev);

        $xpath = new \DOMXPath($dom);

        $dangerous = $xpath->query('//*[local-name()="script"] | //*[local-name()="foreignObject"]');
        if ($dangerous !== false) {
            foreach ($dangerous as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $allElements = $xpath->query('//*');
        if ($allElements !== false) {
            foreach ($allElements as $element) {
                /** @var \DOMElement $element */
                $toRemove = [];
                foreach ($element->attributes as $attr) {
                    $name = strtolower($attr->name);
                    if (
                        str_starts_with($name, 'on')
                        || ($name === 'href' && str_starts_with(strtolower($attr->value), 'javascript:'))
                        || ($name === 'xlink:href' && str_starts_with(strtolower($attr->value), 'javascript:'))
                    ) {
                        $toRemove[] = $attr->name;
                    }
                }
                foreach ($toRemove as $attrName) {
                    $element->removeAttribute($attrName);
                }
            }
        }

        $dom->save($absolutePath);
    }

    private function resolveExtension(string $originalName): string
    {
        $ext     = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'svg'];
        return in_array($ext, $allowed, true) ? $ext : 'bin';
    }

    private function buildResult(string $fileHash): LogoUploadResultInterface
    {
        $result = $this->resultFactory->create();
        $result->setLogoUid($fileHash);
        return $result;
    }

    private function getCustomerId(): ?int
    {
        if ($this->userContext->getUserType() === UserContextInterface::USER_TYPE_CUSTOMER) {
            return (int) $this->userContext->getUserId();
        }
        return null;
    }
}
