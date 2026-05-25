<?php
declare(strict_types=1);

namespace Local\Personpack\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

class SetPersonpackMode implements ResolverInterface
{
    public function __construct(
        private readonly GetCartForUser          $getCartForUser,
        private readonly CartRepositoryInterface $cartRepository
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
        $enabled      = (bool) ($input['enabled'] ?? false);

        if ($maskedCartId === '') {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }

        $currentUserId = $context->getUserId();
        $storeId       = (int) $context->getExtensionAttributes()->getStore()->getId();
        $cart          = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);

        $cart->setData('is_personpack', $enabled ? 1 : 0);

        if (!$enabled) {
            // Clear personpack person data from all items when disabling
            foreach ($cart->getAllItems() as $item) {
                if ($item->getData('personpack_person_id')) {
                    $cart->removeItem($item->getItemId());
                }
            }
            $cart->setData('personpack_people_count', 0);
        }

        $this->cartRepository->save($cart);

        return ['cart' => ['model' => $cart]];
    }
}
