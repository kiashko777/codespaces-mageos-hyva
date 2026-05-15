<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model\Resolver;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Item as QuoteItemResource;
use Magento\QuoteGraphQl\Model\CartItem\DataProvider\UpdateCartItems as UpdateCartItemsDataProvider;
use Workwear\Personalization\Model\CustomerLogoFactory;
use Workwear\Personalization\Model\ResourceModel\CustomerLogo as CustomerLogoResource;
use Workwear\Personalization\Model\ResourceModel\ProductPosition\CollectionFactory as PositionCollectionFactory;

class UpdateCartItemPersonalization
{
    private const MAX_TEXT_LINES = 3;
    private const MAX_TEXT_LINE_LENGTH = 22;
    private const CONTENT_TYPE_LOGO = 'LOGO';
    private const CONTENT_TYPE_TEXT = 'TEXT';

    public function __construct(
        private readonly CustomerLogoFactory $customerLogoFactory,
        private readonly CustomerLogoResource $customerLogoResource,
        private readonly PositionCollectionFactory $positionCollectionFactory,
        private readonly QuoteItemResource $quoteItemResource,
    ) {}

    /**
     * After cart items are updated, validate and persist personalization data on each quote item.
     *
     * @throws GraphQlInputException
     */
    public function afterProcessCartItems(
        UpdateCartItemsDataProvider $subject,
        mixed $result,
        Quote $cart,
        array $items
    ): void {
        foreach ($items as $item) {
            if (!array_key_exists('personalizations', $item)) {
                continue;
            }

            $itemId = (int) ($item['cart_item_id'] ?? 0);
            if ($itemId === 0 && !empty($item['cart_item_uid'])) {
                $itemId = (int) base64_decode((string) $item['cart_item_uid']);
            }
            if ($itemId === 0) {
                continue;
            }

            $cartItem = $cart->getItemById($itemId);
            if (!$cartItem) {
                continue;
            }

            $personalizations = $item['personalizations'];

            if ($personalizations === [] || $personalizations === null) {
                $cartItem->setData('personalization_data', null);
                $this->quoteItemResource->save($cartItem);
                continue;
            }

            $productId = (int) $cartItem->getProductId();

            $this->validatePersonalizations($personalizations, $productId);

            $cartItem->setData(
                'personalization_data',
                json_encode($personalizations, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            );
            $this->quoteItemResource->save($cartItem);
        }
    }

    /**
     * @throws GraphQlInputException
     */
    private function validatePersonalizations(array $personalizations, int $productId): void
    {
        $validPositions = $this->loadValidPositions($productId);
        $seenPositions = [];

        foreach ($personalizations as $p) {
            $positionCode = (string) ($p['position_code'] ?? '');

            if (!in_array($positionCode, $validPositions, true)) {
                throw new GraphQlInputException(__(
                    'Position "%1" is not available for this product.',
                    $positionCode
                ));
            }

            if (in_array($positionCode, $seenPositions, true)) {
                throw new GraphQlInputException(__(
                    'Duplicate position "%1" in personalizations.',
                    $positionCode
                ));
            }
            $seenPositions[] = $positionCode;

            $contentType = (string) ($p['content_type'] ?? '');

            if ($contentType === self::CONTENT_TYPE_LOGO) {
                $this->validateLogoPersonalization($p);
            } elseif ($contentType === self::CONTENT_TYPE_TEXT) {
                $this->validateTextPersonalization($p);
            }
        }
    }

    /**
     * @throws GraphQlInputException
     */
    private function validateLogoPersonalization(array $p): void
    {
        $logoUid = (string) ($p['logo_uid'] ?? '');

        if ($logoUid === '') {
            throw new GraphQlInputException(__(
                'Field "logo_uid" is required when content_type is LOGO.'
            ));
        }

        $logo = $this->customerLogoFactory->create();
        $this->customerLogoResource->loadByFileHash($logo, $logoUid);

        if (!$logo->getId()) {
            throw new GraphQlInputException(__(
                'Logo with uid "%1" does not exist.',
                $logoUid
            ));
        }
    }

    /**
     * @throws GraphQlInputException
     */
    private function validateTextPersonalization(array $p): void
    {
        $textLines = $p['text_lines'] ?? [];

        if (count($textLines) === 0) {
            throw new GraphQlInputException(__('At least one text line is required when content_type is TEXT.'));
        }

        if (count($textLines) > self::MAX_TEXT_LINES) {
            throw new GraphQlInputException(__(
                'Maximum %1 text lines allowed.',
                self::MAX_TEXT_LINES
            ));
        }

        foreach ($textLines as $line) {
            $length = mb_strlen((string) $line);
            if ($length > self::MAX_TEXT_LINE_LENGTH) {
                throw new GraphQlInputException(__(
                    'Text line "%1" exceeds the maximum length of %2 characters.',
                    $line,
                    self::MAX_TEXT_LINE_LENGTH
                ));
            }
        }
    }

    private function loadValidPositions(int $productId): array
    {
        $collection = $this->positionCollectionFactory->create();
        $collection->addFieldToFilter('product_id', ['eq' => $productId]);

        return $collection->getColumnValues('position_code');
    }
}
