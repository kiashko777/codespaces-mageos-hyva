<?php
declare(strict_types=1);

namespace Local\IndividualPacking\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\ResourceModel\Quote\Item as QuoteItemResource;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

class SetIndividualPackingOnCartItem implements ResolverInterface
{
    public function __construct(
        private readonly GetCartForUser    $getCartForUser,
        private readonly QuoteItemResource $quoteItemResource
    ) {}

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        $input        = $args['input'] ?? [];
        $maskedCartId = (string) ($input['cart_id'] ?? '');
        $cartItemId   = (int) ($input['cart_item_id'] ?? 0);
        $selected     = (bool) ($input['individual_packing'] ?? false);

        if ($maskedCartId === '') {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }
        if ($cartItemId <= 0) {
            throw new GraphQlInputException(__('Parameter "cart_item_id" must be a positive integer'));
        }

        $currentUserId = $context->getUserId();
        $storeId       = (int) $context->getExtensionAttributes()->getStore()->getId();
        $cart          = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);

        $cartItem = $cart->getItemById($cartItemId);
        if (!$cartItem) {
            throw new GraphQlInputException(
                __('Cart item with id "%1" does not exist', $cartItemId)
            );
        }

        $cartItem->setData('individual_packing_selected', $selected ? 1 : 0);
        $this->quoteItemResource->save($cartItem);

        return ['model' => $cart];
    }
}
