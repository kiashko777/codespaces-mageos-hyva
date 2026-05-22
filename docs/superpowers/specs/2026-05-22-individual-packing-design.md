# Individual Packing Feature — Design Spec
**Date:** 2026-05-22  
**Module:** `Local_IndividualPacking`  
**Status:** Approved

---

## Overview

Adds a per-cart-item "Individual Packing" option to the workwear store. Customers can request each unit of a qualifying product to be bagged and labelled separately for a fee of £0.49 (configurable). The selection is stored as an order item option so it appears in Admin → Sales → Orders, customer account order history, and email confirmations — alongside personalization data.

---

## Architecture

### Module identity
- **Vendor/Module:** `Local/IndividualPacking`
- **Namespace:** `Local\IndividualPacking`
- **Sequence dependencies:** `Magento_Quote`, `Magento_Sales`, `Magento_GraphQl`, `Magento_Catalog`, `Workwear_Personalization` (load-order only, no hard interface dep)

### File structure
```
app/code/Local/IndividualPacking/
├── registration.php
├── etc/
│   ├── module.xml
│   ├── di.xml                                   # attribute processor, plugin, resolver wiring
│   ├── sales.xml                                # total registration
│   ├── schema.graphqls                          # GraphQL schema extensions
│   ├── config.xml                               # default config values
│   └── adminhtml/
│       └── system.xml                           # admin config fields
├── Setup/
│   └── Patch/
│       └── Data/
│           └── AddIndividuallyPackableAttribute.php
├── Model/
│   ├── Config.php
│   └── Quote/
│       └── Total/
│           └── IndividualPackingFee.php
├── Plugin/
│   └── SaveIndividualPackingToOrderItem.php
└── GraphQl/
    └── Resolver/
        ├── SetIndividualPackingOnCartItem.php
        ├── CartItemIndividualPackingData.php
        └── IndividualPackingFeeResolver.php
```

**Note:** `db_schema.xml` (not a Data Patch) handles the `quote_item` column, consistent with `Workwear_Personalization` which uses the same approach for `personalization_data`.

---

## Components

### 1. Product attribute `individually_packable`

EAV select attribute created via Data Patch `AddIndividuallyPackableAttribute`.

| Option value | Meaning | Frontend behaviour |
|---|---|---|
| `eligible` | Can be individually packed | Show checkbox + fee |
| `pre_packaged` | Ships in sealed packaging | Show info message, no fee |
| `boxed` | Ships in own box (e.g. boots) | Show info message, no fee |
| `not_eligible` | Not available | Hide UI entirely |

- Scope: Global
- Visible in Admin product edit: Yes
- Added to default attribute set

Exposed in GraphQL via `AttributeProcessor` wiring in `di.xml`:
```graphql
interface ProductInterface {
    individually_packable: String
}
```

### 2. Admin configuration

Location: **Stores → Configuration → Workwear → Individual Packing** (uses existing `workwear` tab).

| Field | Type | Default | Config path |
|---|---|---|---|
| Enabled | yes/no select | Yes | `workwear/individual_packing/enabled` |
| Fee Per Item (£) | text | 0.49 | `workwear/individual_packing/fee_per_item` |
| Label | text | Individual Packing | `workwear/individual_packing/label` |

`Model/Config.php` wraps `ScopeConfigInterface` with typed getters: `isEnabled(): bool`, `getFeePerItem(): float`, `getLabel(): string`.

`etc/config.xml` provides defaults so the store works out-of-the-box without admin saving config first.

### 3. Quote item storage

`db_schema.xml` adds column to `quote_item`:
```xml
<column xsi:type="smallint" name="individual_packing_selected"
        unsigned="true" nullable="true" default="0"
        comment="Individual packing selected by customer"/>
```

No separate Data Patch required — declarative schema handles this.

### 4. GraphQL mutation

```graphql
type Mutation {
    setIndividualPackingOnCartItem(
        input: SetIndividualPackingOnCartItemInput!
    ): SetIndividualPackingOnCartItemOutput
}

input SetIndividualPackingOnCartItemInput {
    cart_id: String!
    cart_item_id: Int!
    individual_packing: Boolean!
}

type SetIndividualPackingOnCartItemOutput {
    cart: Cart!
}
```

Resolver `SetIndividualPackingOnCartItem`:
1. Loads cart via `MaskedQuoteIdToQuoteIdInterface` + `CartRepositoryInterface`
2. Validates customer owns the cart
3. Finds item by `cart_item_id`
4. Sets `individual_packing_selected` on the quote item
5. Saves via `QuoteItemResource::save()`
6. Returns resolved cart

### 5. Cart total

`Model/Quote/Total/IndividualPackingFee` extends `AbstractTotal`.

Fee calculation:
```
total_fee = fee_per_item × SUM(qty for items where individual_packing_selected = 1)
```

Only runs when module is enabled. Returns zero (and adds nothing to totals) when disabled.

Registered in `etc/sales.xml` at sort_order 160 (after `personalization_fee` at 150, before tax):
```xml
<item name="individual_packing_fee"
      instance="Local\IndividualPacking\Model\Quote\Total\IndividualPackingFee"
      sort_order="160"/>
```

GraphQL exposure on `Cart` type:
```graphql
extend type Cart {
    individual_packing_fee: Float
    individual_packing_fee_formatted: String
}
```

`IndividualPackingFeeResolver` reads the total from `$cart->getTotals()['individual_packing_fee']`.

### 6. Order item options (critical path)

Plugin `SaveIndividualPackingToOrderItem` on `Magento\Quote\Model\Quote\Item\ToOrderItem` using `afterConvert`.

When `individual_packing_selected` is truthy on the quote item, appends to `product_options['additional_options']`:

```php
[
    ['label' => 'Individual Packing',     'value' => 'Yes — each item bagged and labelled separately'],
    ['label' => 'Individual Packing Fee', 'value' => '£X.XX (£0.49 × N)'],
]
```

This makes the option appear in:
- Admin → Sales → Orders → Items Ordered tab
- Customer account → Order History → Order Details
- Order confirmation emails (Magento renders `additional_options` automatically in email templates)

Merge pattern: reads existing `$orderItem->getProductOptions() ?? []` then merges, identical to `Workwear_Personalization\Plugin\Quote\Item\ToOrderItemPlugin`.

### 7. CartItemInterface extension

```graphql
extend interface CartItemInterface {
    individual_packing_selected: Boolean
}
```

Resolver `CartItemIndividualPackingData` reads `$value['model']->getData('individual_packing_selected')`, casts to bool.

---

## Data flow

```
Customer selects packing in frontend
    → setIndividualPackingOnCartItem mutation
        → quote_item.individual_packing_selected = 1
            → IndividualPackingFee total collects → adds to cart totals
            → Order placement: ToOrderItem plugin → additional_options appended
                → Visible in Admin, customer account, email
```

---

## GraphQL query example

```graphql
{
  cart(cart_id: "xxx") {
    individual_packing_fee
    individual_packing_fee_formatted
    items {
      id
      quantity
      individual_packing_selected
      product {
        individually_packable
      }
    }
  }
}
```

---

## Key design decisions

1. **`db_schema.xml` over Data Patch for quote column** — Magento's declarative schema is the correct tool for DDL changes; Data Patches are for DML (data migration). Consistent with `Workwear_Personalization`.

2. **`sales.xml` for total registration** — Magento reads cart totals from `sales.xml`, not from `di.xml` type arguments. The spec's suggestion to use `di.xml` would not work.

3. **`afterConvert` for the order item plugin** — Simpler than `aroundConvert` since we only need to inspect the result, not intercept the call. Safe because no other plugin should clear `additional_options` after us.

4. **Standalone mutation, not extending `updateCartItems`** — Personalization extends `updateCartItems` because it's tightly coupled to item update flow. Individual packing is a binary toggle that warrants a dedicated, clearly-named mutation.

5. **Workwear tab in admin** — Feature is part of the workwear domain; Sales section is for shipping/payment/tax. Keeps workwear config consolidated.

---

## Verification steps

After `setup:upgrade` + `setup:di:compile` + `cache:flush`:

1. Admin → Catalog → Products → edit any product → "Individual Packing" dropdown present
2. Set product to `eligible`
3. Create cart via GraphQL, add product, call `setIndividualPackingOnCartItem` with `individual_packing: true`
4. Query cart → `individual_packing_fee` and `individual_packing_selected` both return expected values
5. Place order → Admin → Sales → Orders → open order → "Individual Packing: Yes" in Items Ordered
6. Check customer account order history and order email for same option display
