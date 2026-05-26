<?php
declare(strict_types=1);

namespace Local\Personpack\GraphQl\Resolver;

use Local\Personpack\Model\Config;
use Local\Personpack\Model\PersonpackCalculator;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\ResourceModel\Quote\Item as QuoteItemResource;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

class SetPersonpackItems implements ResolverInterface
{
    public function __construct(
        private readonly GetCartForUser            $getCartForUser,
        private readonly CartRepositoryInterface   $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly QuoteItemResource         $quoteItemResource,
        private readonly Config                    $config,
        private readonly PersonpackCalculator      $personpackCalculator
    ) {}

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        if (!$this->config->isEnabled()) {
            throw new GraphQlInputException(__('Personpack mode is not available.'));
        }

        $input        = $args['input'] ?? [];
        $maskedCartId = (string) ($input['cart_id'] ?? '');
        $people       = $input['people'] ?? [];

        if ($maskedCartId === '') {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }
        if (empty($people)) {
            throw new GraphQlInputException(__('At least one person must be provided'));
        }

        $currentUserId = $context->getUserId();
        $storeId       = (int) $context->getExtensionAttributes()->getStore()->getId();
        $cart          = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);

        // Remove ALL existing items
        foreach ($cart->getAllItems() as $item) {
            $cart->removeItem($item->getItemId());
        }

        // Add items per person
        $sequence = 1;
        foreach ($people as $person) {
            $personId   = (string) ($person['person_id'] ?? '');
            $personName = (string) ($person['person_name'] ?? '');
            $items      = $person['items'] ?? [];

            if ($personId === '' || $personName === '') {
                throw new GraphQlInputException(__('Each person must have a person_id and person_name'));
            }

            foreach ($items as $personItem) {
                $sku = (string) ($personItem['sku'] ?? '');
                $qty = (int) ($personItem['qty'] ?? 1);

                if ($sku === '') {
                    throw new GraphQlInputException(__('Each item must have a sku'));
                }

                $product = $this->productRepository->get($sku, false, $storeId, true);
                $product->addCustomOption('personpack_person_id', $personId);

                $quoteItem = $cart->addProduct($product, $qty);

                if (is_string($quoteItem)) {
                    throw new GraphQlInputException(__($quoteItem));
                }

                // Set person data on the main item
                $quoteItem->setData('personpack_person_id', $personId);
                $quoteItem->setData('personpack_person_name', $personName);
                $quoteItem->setData('personpack_sequence', $sequence);

                // Also set on parent item if exists (configurable product creates parent+child)
                $parentItem = $quoteItem->getParentItem();
                if ($parentItem) {
                    $parentItem->setData('personpack_person_id', $personId);
                    $parentItem->setData('personpack_person_name', $personName);
                    $parentItem->setData('personpack_sequence', $sequence);
                }
            }

            $sequence++;
        }

        $cart->setData('is_personpack', 1);
        $cart->setData('personpack_people_count', $this->personpackCalculator->countPeople($cart));
        $this->cartRepository->save($cart);

        // Persist personpack fields on all items
        foreach ($cart->getAllItems() as $item) {
            if ($item->getData('personpack_person_id')) {
                $this->quoteItemResource->save($item);
            }
        }

        return ['cart' => ['model' => $cart]];
    }
}
