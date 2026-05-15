<?php

declare(strict_types=1);

namespace Workwear\Personalization\Plugin\GraphQl;

use Magento\QuoteGraphQl\Model\CartItem\CartItemsUidArgsProcessor;

/**
 * Tolerance plugin: accept numeric cart_item_uid (e.g. "51") in addition to base64-encoded uids.
 * Some headless clients pass the raw quote_item id; core CartItemsUidArgsProcessor rejects that
 * via Uid::decode. We base64-encode numeric values before the core processor runs so the rest of
 * the pipeline (including Workwear personalizations) executes normally.
 */
class CartItemUidTolerance
{
    public function beforeProcess(
        CartItemsUidArgsProcessor $subject,
        string $fieldName,
        array $args
    ): array {
        if (empty($args['input']['cart_items']) || !is_array($args['input']['cart_items'])) {
            return [$fieldName, $args];
        }

        foreach ($args['input']['cart_items'] as $key => $cartItem) {
            $uid = $cartItem['cart_item_uid'] ?? null;
            if ($uid !== null && $uid !== '' && ctype_digit((string) $uid)) {
                $args['input']['cart_items'][$key]['cart_item_uid'] = base64_encode((string) $uid);
            }
        }

        return [$fieldName, $args];
    }
}
