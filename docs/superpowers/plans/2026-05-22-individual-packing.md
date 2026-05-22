# Individual Packing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a per-cart-item "Individual Packing" option (£0.49/unit, configurable) that stores as an order item option so it appears in Admin, customer account history, and email confirmations.

**Architecture:** New module `Local_IndividualPacking` mirrors the `Workwear_Personalization` module's patterns — `db_schema.xml` for DDL, `sales.xml` for total registration, `afterConvert` plugin for order item options, and dedicated GraphQL resolvers. Fee is `config_fee × qty` per selected item. Product eligibility is controlled by EAV select attribute `individually_packable`.

**Tech Stack:** PHP 8.3, Magento 2 / Mage-OS 2.2.1, GraphQL, PHPUnit 10.x, Declarative Schema

---

## File Map

| File | Responsibility |
|---|---|
| `registration.php` | Component registration |
| `etc/module.xml` | Module declaration + sequence |
| `etc/di.xml` | Plugin declaration + AttributeProcessor wiring |
| `etc/sales.xml` | Cart total registration |
| `etc/config.xml` | Default config values |
| `etc/schema.graphqls` | All GraphQL schema extensions |
| `etc/db_schema.xml` | `quote_item.individual_packing_selected` column |
| `etc/adminhtml/system.xml` | Admin config UI |
| `Setup/Patch/Data/AddIndividuallyPackableAttribute.php` | EAV select attribute creation |
| `Model/Config.php` | Typed config value reader |
| `Model/Quote/Total/IndividualPackingFee.php` | Cart total collector |
| `Plugin/SaveIndividualPackingToOrderItem.php` | Appends `additional_options` on order conversion |
| `GraphQl/Resolver/SetIndividualPackingOnCartItem.php` | Mutation resolver |
| `GraphQl/Resolver/CartItemIndividualPackingData.php` | `CartItemInterface.individual_packing_selected` |
| `GraphQl/Resolver/IndividualPackingFeeResolver.php` | `Cart.individual_packing_fee` + `..._formatted` |
| `GraphQl/Resolver/IndividuallyPackableResolver.php` | `ProductInterface.individually_packable` |
| `Test/Unit/Model/ConfigTest.php` | Config unit tests |
| `Test/Unit/Model/Quote/Total/IndividualPackingFeeTest.php` | Total unit tests |
| `Test/Unit/Plugin/SaveIndividualPackingToOrderItemTest.php` | Plugin unit tests |

All paths are relative to `app/code/Local/IndividualPacking/`.

---

## Task 1: Module scaffold

**Files:**
- Create: `app/code/Local/IndividualPacking/registration.php`
- Create: `app/code/Local/IndividualPacking/etc/module.xml`

- [ ] **Step 1: Create registration.php**

```php
<?php
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Local_IndividualPacking',
    __DIR__
);
```

- [ ] **Step 2: Create etc/module.xml**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="Local_IndividualPacking">
        <sequence>
            <module name="Magento_Quote"/>
            <module name="Magento_Sales"/>
            <module name="Magento_GraphQl"/>
            <module name="Magento_Catalog"/>
            <module name="Magento_CatalogGraphQl"/>
            <module name="Magento_QuoteGraphQl"/>
            <module name="Workwear_Personalization"/>
        </sequence>
    </module>
</config>
```

- [ ] **Step 3: Enable the module**

```bash
bin/magento module:enable Local_IndividualPacking
```

Expected: `The following modules have been enabled: Local_IndividualPacking`

- [ ] **Step 4: Commit**

```bash
git add app/code/Local/IndividualPacking/registration.php app/code/Local/IndividualPacking/etc/module.xml app/etc/config.php
git commit -m "feat(individual-packing): scaffold Local_IndividualPacking module"
```

---

## Task 2: Config model — TDD

**Files:**
- Create: `app/code/Local/IndividualPacking/Model/Config.php`
- Create: `app/code/Local/IndividualPacking/Test/Unit/Model/ConfigTest.php`

- [ ] **Step 1: Write the failing tests**

Create `app/code/Local/IndividualPacking/Test/Unit/Model/ConfigTest.php`:

```php
<?php
declare(strict_types=1);

namespace Local\IndividualPacking\Test\Unit\Model;

use Local\IndividualPacking\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private Config $config;
    private ScopeConfigInterface|MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    public function testIsEnabledReturnsTrueWhenFlagSet(): void
    {
        $this->scopeConfig
            ->method('isSetFlag')
            ->with('workwear/individual_packing/enabled', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        self::assertTrue($this->config->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenFlagNotSet(): void
    {
        $this->scopeConfig
            ->method('isSetFlag')
            ->with('workwear/individual_packing/enabled', ScopeInterface::SCOPE_STORE)
            ->willReturn(false);

        self::assertFalse($this->config->isEnabled());
    }

    public function testGetFeePerItemReturnsCastFloat(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with('workwear/individual_packing/fee_per_item', ScopeInterface::SCOPE_STORE)
            ->willReturn('0.49');

        self::assertSame(0.49, $this->config->getFeePerItem());
    }

    public function testGetLabelReturnsConfiguredLabel(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with('workwear/individual_packing/label', ScopeInterface::SCOPE_STORE)
            ->willReturn('Individual Packing');

        self::assertSame('Individual Packing', $this->config->getLabel());
    }

    public function testGetLabelReturnsDefaultWhenNull(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with('workwear/individual_packing/label', ScopeInterface::SCOPE_STORE)
            ->willReturn(null);

        self::assertSame('Individual Packing', $this->config->getLabel());
    }
}
```

- [ ] **Step 2: Run tests — expect failure**

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml app/code/Local/IndividualPacking/Test/Unit/Model/ConfigTest.php
```

Expected: Class `Local\IndividualPacking\Model\Config` not found.

- [ ] **Step 3: Implement Config.php**

Create `app/code/Local/IndividualPacking/Model/Config.php`:

```php
<?php
declare(strict_types=1);

namespace Local\IndividualPacking\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED   = 'workwear/individual_packing/enabled';
    private const XML_PATH_FEE       = 'workwear/individual_packing/fee_per_item';
    private const XML_PATH_LABEL     = 'workwear/individual_packing/label';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getFeePerItem(): float
    {
        return (float) $this->scopeConfig->getValue(self::XML_PATH_FEE, ScopeInterface::SCOPE_STORE);
    }

    public function getLabel(): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_LABEL, ScopeInterface::SCOPE_STORE)
            ?? 'Individual Packing');
    }
}
```

- [ ] **Step 4: Run tests — expect pass**

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml app/code/Local/IndividualPacking/Test/Unit/Model/ConfigTest.php
```

Expected: `OK (5 tests, 5 assertions)`

- [ ] **Step 5: Commit**

```bash
git add app/code/Local/IndividualPacking/Model/Config.php \
        app/code/Local/IndividualPacking/Test/Unit/Model/ConfigTest.php
git commit -m "feat(individual-packing): add Config model with unit tests"
```

---

## Task 3: Admin config

**Files:**
- Create: `app/code/Local/IndividualPacking/etc/adminhtml/system.xml`
- Create: `app/code/Local/IndividualPacking/etc/config.xml`

- [ ] **Step 1: Create etc/adminhtml/system.xml**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Config:etc/system_file.xsd">
    <system>
        <section id="workwear" translate="label" type="text" sortOrder="100"
                 showInDefault="1" showInWebsite="1" showInStore="1">
            <group id="individual_packing" translate="label" type="text" sortOrder="20"
                   showInDefault="1" showInWebsite="1" showInStore="1">
                <label>Individual Packing</label>
                <field id="enabled" translate="label" type="select" sortOrder="10"
                       showInDefault="1" showInWebsite="1" showInStore="1" canRestore="1">
                    <label>Enabled</label>
                    <source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
                </field>
                <field id="fee_per_item" translate="label comment" type="text" sortOrder="20"
                       showInDefault="1" showInWebsite="1" showInStore="1" canRestore="1">
                    <label>Fee Per Item (£)</label>
                    <comment>Charged per unit when individual packing is selected. Default: 0.49</comment>
                    <validate>validate-number validate-zero-or-greater</validate>
                </field>
                <field id="label" translate="label comment" type="text" sortOrder="30"
                       showInDefault="1" showInWebsite="1" showInStore="1" canRestore="1">
                    <label>Order Line Label</label>
                    <comment>Label shown in order totals and admin order view.</comment>
                </field>
            </group>
        </section>
    </system>
</config>
```

- [ ] **Step 2: Create etc/config.xml**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Store:etc/config.xsd">
    <default>
        <workwear>
            <individual_packing>
                <enabled>1</enabled>
                <fee_per_item>0.49</fee_per_item>
                <label>Individual Packing</label>
            </individual_packing>
        </workwear>
    </default>
</config>
```

- [ ] **Step 3: Commit**

```bash
git add app/code/Local/IndividualPacking/etc/adminhtml/system.xml \
        app/code/Local/IndividualPacking/etc/config.xml
git commit -m "feat(individual-packing): add admin config under Workwear tab"
```

---

## Task 4: Database schema — quote_item column

**Files:**
- Create: `app/code/Local/IndividualPacking/etc/db_schema.xml`

- [ ] **Step 1: Create etc/db_schema.xml**

```xml
<?xml version="1.0"?>
<schema xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd">
    <table name="quote_item" resource="checkout">
        <column xsi:type="smallint" name="individual_packing_selected"
                unsigned="true" nullable="true" default="0"
                comment="Individual packing selected (1=yes, 0=no)"/>
    </table>
</schema>
```

- [ ] **Step 2: Commit**

```bash
git add app/code/Local/IndividualPacking/etc/db_schema.xml
git commit -m "feat(individual-packing): add individual_packing_selected column to quote_item"
```

---

## Task 5: Product attribute Data Patch

**Files:**
- Create: `app/code/Local/IndividualPacking/Setup/Patch/Data/AddIndividuallyPackableAttribute.php`

- [ ] **Step 1: Create the Data Patch**

```php
<?php
declare(strict_types=1);

namespace Local\IndividualPacking\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddIndividuallyPackableAttribute implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory          $eavSetupFactory,
        private readonly CategorySetupFactory     $categorySetupFactory
    ) {}

    public function apply(): static
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->addAttribute(
            Product::ENTITY,
            'individually_packable',
            [
                'type'                    => 'int',
                'label'                   => 'Individual Packing',
                'input'                   => 'select',
                'source'                  => \Magento\Eav\Model\Entity\Attribute\Source\Table::class,
                'required'                => false,
                'sort_order'              => 200,
                'global'                  => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible'                 => true,
                'used_in_product_listing' => true,
                'user_defined'            => true,
                'default'                 => null,
                'option'                  => [
                    'values' => [
                        'eligible',
                        'pre_packaged',
                        'boxed',
                        'not_eligible',
                    ],
                ],
            ]
        );

        $categorySetup    = $this->categorySetupFactory->create(['setup' => $this->moduleDataSetup]);
        $attributeSetId   = $categorySetup->getDefaultAttributeSetId(Product::ENTITY);
        $attributeGroupId = $categorySetup->getDefaultAttributeGroupId(Product::ENTITY, $attributeSetId);

        $categorySetup->addAttributeToSet(
            Product::ENTITY,
            $attributeSetId,
            $attributeGroupId,
            'individually_packable'
        );

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/code/Local/IndividualPacking/Setup/Patch/Data/AddIndividuallyPackableAttribute.php
git commit -m "feat(individual-packing): add individually_packable EAV select attribute patch"
```

---

## Task 6: GraphQL schema

**Files:**
- Create: `app/code/Local/IndividualPacking/etc/schema.graphqls`

- [ ] **Step 1: Create schema.graphqls**

```graphql
type Mutation {
    setIndividualPackingOnCartItem(
        input: SetIndividualPackingOnCartItemInput!
    ): SetIndividualPackingOnCartItemOutput
        @resolver(class: "\\Local\\IndividualPacking\\GraphQl\\Resolver\\SetIndividualPackingOnCartItem")
        @doc(description: "Select or deselect individual packing for a cart item")
}

input SetIndividualPackingOnCartItemInput @doc(description: "Input for setIndividualPackingOnCartItem") {
    cart_id: String! @doc(description: "Masked cart ID")
    cart_item_id: Int! @doc(description: "Numeric cart item ID")
    individual_packing: Boolean! @doc(description: "True to enable individual packing for this item")
}

type SetIndividualPackingOnCartItemOutput @doc(description: "Output for setIndividualPackingOnCartItem") {
    cart: Cart! @doc(description: "The updated cart")
}

extend interface CartItemInterface {
    individual_packing_selected: Boolean
        @doc(description: "Whether individual packing is selected for this cart item")
        @resolver(class: "\\Local\\IndividualPacking\\GraphQl\\Resolver\\CartItemIndividualPackingData")
}

extend type Cart {
    individual_packing_fee: Float
        @doc(description: "Total individual packing fee for the cart")
        @resolver(class: "\\Local\\IndividualPacking\\GraphQl\\Resolver\\IndividualPackingFeeResolver")
    individual_packing_fee_formatted: String
        @doc(description: "Total individual packing fee formatted as currency string")
        @resolver(class: "\\Local\\IndividualPacking\\GraphQl\\Resolver\\IndividualPackingFeeResolver")
}

interface ProductInterface {
    individually_packable: String
        @doc(description: "Individual packing eligibility: eligible, pre_packaged, boxed, not_eligible")
        @resolver(class: "\\Local\\IndividualPacking\\GraphQl\\Resolver\\IndividuallyPackableResolver")
}
```

- [ ] **Step 2: Commit**

```bash
git add app/code/Local/IndividualPacking/etc/schema.graphqls
git commit -m "feat(individual-packing): add GraphQL schema extensions"
```

---

## Task 7: Dependency injection wiring

**Files:**
- Create: `app/code/Local/IndividualPacking/etc/di.xml`

- [ ] **Step 1: Create etc/di.xml**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <!-- Append individually_packable to product collection queries via GraphQL -->
    <type name="Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor\AttributeProcessor">
        <arguments>
            <argument name="additionalAttributes" xsi:type="array">
                <item name="individually_packable" xsi:type="string">individually_packable</item>
            </argument>
        </arguments>
    </type>

    <!-- Append individual packing options to order item on quote→order conversion -->
    <type name="Magento\Quote\Model\Quote\Item\ToOrderItem">
        <plugin name="local_individual_packing_to_order_item"
                type="Local\IndividualPacking\Plugin\SaveIndividualPackingToOrderItem"
                sortOrder="20"/>
    </type>

</config>
```

- [ ] **Step 2: Commit**

```bash
git add app/code/Local/IndividualPacking/etc/di.xml
git commit -m "feat(individual-packing): wire DI — AttributeProcessor and ToOrderItem plugin"
```

---

## Task 8: Cart total — TDD

**Files:**
- Create: `app/code/Local/IndividualPacking/etc/sales.xml`
- Create: `app/code/Local/IndividualPacking/Model/Quote/Total/IndividualPackingFee.php`
- Create: `app/code/Local/IndividualPacking/Test/Unit/Model/Quote/Total/IndividualPackingFeeTest.php`

- [ ] **Step 1: Write failing tests**

Create `app/code/Local/IndividualPacking/Test/Unit/Model/Quote/Total/IndividualPackingFeeTest.php`:

```php
<?php
declare(strict_types=1);

namespace Local\IndividualPacking\Test\Unit\Model\Quote\Total;

use Local\IndividualPacking\Model\Config;
use Local\IndividualPacking\Model\Quote\Total\IndividualPackingFee;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndividualPackingFeeTest extends TestCase
{
    private IndividualPackingFee $totalModel;
    private Config|MockObject $config;

    protected function setUp(): void
    {
        $this->config     = $this->createMock(Config::class);
        $this->totalModel = new IndividualPackingFee($this->config);
    }

    private function makeAssignment(string $addressType): ShippingAssignmentInterface
    {
        $address = $this->createMock(Address::class);
        $address->method('getAddressType')->willReturn($addressType);

        $shipping = $this->createMock(ShippingInterface::class);
        $shipping->method('getAddress')->willReturn($address);

        $assignment = $this->createMock(ShippingAssignmentInterface::class);
        $assignment->method('getShipping')->willReturn($shipping);

        return $assignment;
    }

    public function testCollectSkipsBillingAddress(): void
    {
        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);

        $total->expects(self::never())->method('setTotalAmount');

        $this->totalModel->collect($quote, $this->makeAssignment(Address::TYPE_BILLING), $total);
    }

    public function testCollectSkipsWhenModuleDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);

        $total->expects(self::never())->method('setTotalAmount');

        $this->totalModel->collect($quote, $this->makeAssignment(Address::TYPE_SHIPPING), $total);
    }

    public function testCollectSetsTotalForSelectedItems(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getFeePerItem')->willReturn(0.5);

        $item1 = $this->createMock(Quote\Item::class);
        $item1->method('getData')->with('individual_packing_selected')->willReturn('1');
        $item1->method('getQty')->willReturn(2.0);

        $item2 = $this->createMock(Quote\Item::class);
        $item2->method('getData')->with('individual_packing_selected')->willReturn('0');

        $quote = $this->createMock(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$item1, $item2]);

        $total = $this->createMock(Total::class);
        $total->expects(self::once())
            ->method('setTotalAmount')
            ->with('individual_packing_fee', 1.0);
        $total->expects(self::once())
            ->method('setBaseTotalAmount')
            ->with('individual_packing_fee', 1.0);

        $this->totalModel->collect($quote, $this->makeAssignment(Address::TYPE_SHIPPING), $total);
    }

    public function testCollectDoesNotSetTotalWhenNoItemsSelected(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getFeePerItem')->willReturn(0.49);

        $item = $this->createMock(Quote\Item::class);
        $item->method('getData')->with('individual_packing_selected')->willReturn('0');

        $quote = $this->createMock(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$item]);

        $total = $this->createMock(Total::class);
        $total->expects(self::never())->method('setTotalAmount');

        $this->totalModel->collect($quote, $this->makeAssignment(Address::TYPE_SHIPPING), $total);
    }

    public function testFetchReturnsEmptyWhenNoAmount(): void
    {
        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);
        $total->method('getTotalAmount')->with('individual_packing_fee')->willReturn(null);

        self::assertSame([], $this->totalModel->fetch($quote, $total));
    }

    public function testFetchReturnsCodeAndValue(): void
    {
        $this->config->method('getLabel')->willReturn('Individual Packing');

        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);
        $total->method('getTotalAmount')->with('individual_packing_fee')->willReturn(0.98);

        $result = $this->totalModel->fetch($quote, $total);

        self::assertSame('individual_packing_fee', $result['code']);
        self::assertSame(0.98, $result['value']);
    }
}
```

- [ ] **Step 2: Run tests — expect failure**

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml \
  app/code/Local/IndividualPacking/Test/Unit/Model/Quote/Total/IndividualPackingFeeTest.php
```

Expected: Class `Local\IndividualPacking\Model\Quote\Total\IndividualPackingFee` not found.

- [ ] **Step 3: Create etc/sales.xml**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Sales:etc/sales.xsd">
    <section name="quote">
        <group name="totals">
            <!-- After personalization_fee (150), before tax_subtotal (200) -->
            <item name="individual_packing_fee"
                  instance="Local\IndividualPacking\Model\Quote\Total\IndividualPackingFee"
                  sort_order="160"/>
        </group>
    </section>
</config>
```

- [ ] **Step 4: Create Model/Quote/Total/IndividualPackingFee.php**

```php
<?php
declare(strict_types=1);

namespace Local\IndividualPacking\Model\Quote\Total;

use Local\IndividualPacking\Model\Config;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;

class IndividualPackingFee extends AbstractTotal
{
    protected $_code = 'individual_packing_fee';

    public function __construct(
        private readonly Config $config
    ) {}

    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ): static {
        parent::collect($quote, $shippingAssignment, $total);

        if ($shippingAssignment->getShipping()->getAddress()->getAddressType() !== Quote\Address::TYPE_SHIPPING) {
            return $this;
        }

        if (!$this->config->isEnabled()) {
            return $this;
        }

        $totalFee = 0.0;
        foreach ($quote->getAllVisibleItems() as $item) {
            if ((int) $item->getData('individual_packing_selected') === 1) {
                $totalFee += $this->config->getFeePerItem() * (float) $item->getQty();
            }
        }

        $totalFee = round($totalFee, 2);

        if ($totalFee > 0.0) {
            $total->setTotalAmount($this->getCode(), $totalFee);
            $total->setBaseTotalAmount($this->getCode(), $totalFee);
        }

        return $this;
    }

    public function fetch(Quote $quote, Total $total): array
    {
        $amount = $total->getTotalAmount($this->getCode());
        if (!$amount) {
            return [];
        }

        return [
            'code'  => $this->getCode(),
            'title' => __($this->config->getLabel()),
            'value' => $amount,
        ];
    }
}
```

- [ ] **Step 5: Run tests — expect pass**

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml \
  app/code/Local/IndividualPacking/Test/Unit/Model/Quote/Total/IndividualPackingFeeTest.php
```

Expected: `OK (6 tests, 8 assertions)`

- [ ] **Step 6: Commit**

```bash
git add app/code/Local/IndividualPacking/etc/sales.xml \
        app/code/Local/IndividualPacking/Model/Quote/Total/IndividualPackingFee.php \
        app/code/Local/IndividualPacking/Test/Unit/Model/Quote/Total/IndividualPackingFeeTest.php
git commit -m "feat(individual-packing): add IndividualPackingFee cart total with unit tests"
```

---

## Task 9: SaveIndividualPackingToOrderItem plugin — TDD

**Files:**
- Create: `app/code/Local/IndividualPacking/Plugin/SaveIndividualPackingToOrderItem.php`
- Create: `app/code/Local/IndividualPacking/Test/Unit/Plugin/SaveIndividualPackingToOrderItemTest.php`

- [ ] **Step 1: Write failing tests**

Create `app/code/Local/IndividualPacking/Test/Unit/Plugin/SaveIndividualPackingToOrderItemTest.php`:

```php
<?php
declare(strict_types=1);

namespace Local\IndividualPacking\Test\Unit\Plugin;

use Local\IndividualPacking\Model\Config;
use Local\IndividualPacking\Plugin\SaveIndividualPackingToOrderItem;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SaveIndividualPackingToOrderItemTest extends TestCase
{
    private SaveIndividualPackingToOrderItem $plugin;
    private Config|MockObject $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->plugin = new SaveIndividualPackingToOrderItem($this->config);
    }

    public function testDoesNotAddOptionsWhenNotSelected(): void
    {
        $subject   = $this->createMock(ToOrderItem::class);
        $orderItem = $this->createMock(OrderItem::class);
        $quoteItem = $this->createMock(QuoteItem::class);

        $quoteItem->method('getData')
            ->with('individual_packing_selected')
            ->willReturn('0');

        $orderItem->expects(self::never())->method('setProductOptions');

        $result = $this->plugin->afterConvert($subject, $orderItem, $quoteItem);

        self::assertSame($orderItem, $result);
    }

    public function testAddsAdditionalOptionsWhenSelected(): void
    {
        $this->config->method('getFeePerItem')->willReturn(0.49);

        $subject   = $this->createMock(ToOrderItem::class);
        $quoteItem = $this->createMock(QuoteItem::class);
        $quoteItem->method('getData')
            ->with('individual_packing_selected')
            ->willReturn('1');
        $quoteItem->method('getQty')->willReturn(2.0);

        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getProductOptions')->willReturn([]);

        $captured = null;
        $orderItem->method('setProductOptions')
            ->willReturnCallback(function (array $opts) use (&$captured, $orderItem) {
                $captured = $opts;
                return $orderItem;
            });

        $this->plugin->afterConvert($subject, $orderItem, $quoteItem);

        $additional = $captured['additional_options'];
        self::assertCount(2, $additional);
        self::assertSame('Individual Packing', $additional[0]['label']);
        self::assertStringContainsString('Yes', $additional[0]['value']);
        self::assertSame('Individual Packing Fee', $additional[1]['label']);
        self::assertStringContainsString('£0.98', $additional[1]['value']);
        self::assertStringContainsString('£0.49', $additional[1]['value']);
        self::assertStringContainsString('× 2', $additional[1]['value']);
    }

    public function testPreservesExistingAdditionalOptions(): void
    {
        $this->config->method('getFeePerItem')->willReturn(0.49);

        $subject   = $this->createMock(ToOrderItem::class);
        $quoteItem = $this->createMock(QuoteItem::class);
        $quoteItem->method('getData')
            ->with('individual_packing_selected')
            ->willReturn('1');
        $quoteItem->method('getQty')->willReturn(1.0);

        $existing  = [['label' => 'Personalisation', 'value' => 'LEFT_CHEST']];
        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getProductOptions')
            ->willReturn(['additional_options' => $existing]);

        $captured = null;
        $orderItem->method('setProductOptions')
            ->willReturnCallback(function (array $opts) use (&$captured) {
                $captured = $opts;
            });

        $this->plugin->afterConvert($subject, $orderItem, $quoteItem);

        self::assertCount(3, $captured['additional_options']);
        self::assertSame('Personalisation', $captured['additional_options'][0]['label']);
        self::assertSame('Individual Packing', $captured['additional_options'][1]['label']);
    }
}
```

- [ ] **Step 2: Run tests — expect failure**

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml \
  app/code/Local/IndividualPacking/Test/Unit/Plugin/SaveIndividualPackingToOrderItemTest.php
```

Expected: Class `Local\IndividualPacking\Plugin\SaveIndividualPackingToOrderItem` not found.

- [ ] **Step 3: Implement the plugin**

Create `app/code/Local/IndividualPacking/Plugin/SaveIndividualPackingToOrderItem.php`:

```php
<?php
declare(strict_types=1);

namespace Local\IndividualPacking\Plugin;

use Local\IndividualPacking\Model\Config;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Model\Order\Item as OrderItem;

class SaveIndividualPackingToOrderItem
{
    public function __construct(
        private readonly Config $config
    ) {}

    public function afterConvert(
        ToOrderItem $subject,
        OrderItem $orderItem,
        QuoteItem $quoteItem,
        array $data = []
    ): OrderItem {
        if (!(int) $quoteItem->getData('individual_packing_selected')) {
            return $orderItem;
        }

        $qty        = (float) $quoteItem->getQty();
        $feePerItem = $this->config->getFeePerItem();
        $totalFee   = round($qty * $feePerItem, 2);

        $options = $orderItem->getProductOptions() ?? [];
        $options['additional_options'] = array_merge(
            $options['additional_options'] ?? [],
            [
                [
                    'label' => 'Individual Packing',
                    'value' => 'Yes — each item bagged and labelled separately',
                ],
                [
                    'label' => 'Individual Packing Fee',
                    'value' => '£' . number_format($totalFee, 2)
                        . ' (£' . number_format($feePerItem, 2) . ' × ' . (int) $qty . ')',
                ],
            ]
        );
        $orderItem->setProductOptions($options);

        return $orderItem;
    }
}
```

- [ ] **Step 4: Run tests — expect pass**

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml \
  app/code/Local/IndividualPacking/Test/Unit/Plugin/SaveIndividualPackingToOrderItemTest.php
```

Expected: `OK (3 tests, 8 assertions)`

- [ ] **Step 5: Run all module tests**

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml \
  app/code/Local/IndividualPacking/Test/Unit/
```

Expected: `OK (14 tests, 21 assertions)` (all three test files)

- [ ] **Step 6: Commit**

```bash
git add app/code/Local/IndividualPacking/Plugin/SaveIndividualPackingToOrderItem.php \
        app/code/Local/IndividualPacking/Test/Unit/Plugin/SaveIndividualPackingToOrderItemTest.php
git commit -m "feat(individual-packing): add ToOrderItem plugin to append additional_options with unit tests"
```

---

## Task 10: SetIndividualPackingOnCartItem resolver

**Files:**
- Create: `app/code/Local/IndividualPacking/GraphQl/Resolver/SetIndividualPackingOnCartItem.php`

- [ ] **Step 1: Implement the resolver**

```php
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
```

- [ ] **Step 2: Commit**

```bash
git add app/code/Local/IndividualPacking/GraphQl/Resolver/SetIndividualPackingOnCartItem.php
git commit -m "feat(individual-packing): add setIndividualPackingOnCartItem mutation resolver"
```

---

## Task 11: CartItemIndividualPackingData resolver

**Files:**
- Create: `app/code/Local/IndividualPacking/GraphQl/Resolver/CartItemIndividualPackingData.php`

- [ ] **Step 1: Implement the resolver**

```php
<?php
declare(strict_types=1);

namespace Local\IndividualPacking\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class CartItemIndividualPackingData implements ResolverInterface
{
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): ?bool {
        $cartItem = $value['model'] ?? null;
        if ($cartItem === null) {
            return null;
        }

        return (bool) $cartItem->getData('individual_packing_selected');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/code/Local/IndividualPacking/GraphQl/Resolver/CartItemIndividualPackingData.php
git commit -m "feat(individual-packing): add CartItemIndividualPackingData resolver"
```

---

## Task 12: IndividualPackingFeeResolver + IndividuallyPackableResolver

**Files:**
- Create: `app/code/Local/IndividualPacking/GraphQl/Resolver/IndividualPackingFeeResolver.php`
- Create: `app/code/Local/IndividualPacking/GraphQl/Resolver/IndividuallyPackableResolver.php`

- [ ] **Step 1: Create IndividualPackingFeeResolver.php**

Both `individual_packing_fee` and `individual_packing_fee_formatted` are routed to this resolver. The field name distinguishes the return type.

```php
<?php
declare(strict_types=1);

namespace Local\IndividualPacking\GraphQl\Resolver;

use Local\IndividualPacking\Model\Config;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class IndividualPackingFeeResolver implements ResolverInterface
{
    public function __construct(
        private readonly Config $config
    ) {}

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): float|string|null {
        $cart = $value['model'] ?? null;
        if ($cart === null) {
            return null;
        }

        $fee = 0.0;
        foreach ($cart->getAllVisibleItems() as $item) {
            if ((int) $item->getData('individual_packing_selected') === 1) {
                $fee += $this->config->getFeePerItem() * (float) $item->getQty();
            }
        }
        $fee = round($fee, 2);

        if ($field->getName() === 'individual_packing_fee_formatted') {
            return '£' . number_format($fee, 2);
        }

        return $fee;
    }
}
```

- [ ] **Step 2: Create IndividuallyPackableResolver.php**

`getData('individually_packable')` returns the option ID. `getAttributeText()` translates it to the label string ('eligible', 'pre_packaged', etc.) as set by the Data Patch.

```php
<?php
declare(strict_types=1);

namespace Local\IndividualPacking\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class IndividuallyPackableResolver implements ResolverInterface
{
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): ?string {
        $product = $value['model'] ?? null;
        if ($product === null) {
            return null;
        }

        $optionId = $product->getData('individually_packable');
        if ($optionId === null || $optionId === '') {
            return null;
        }

        return $product->getAttributeText('individually_packable') ?: null;
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/code/Local/IndividualPacking/GraphQl/Resolver/IndividualPackingFeeResolver.php \
        app/code/Local/IndividualPacking/GraphQl/Resolver/IndividuallyPackableResolver.php
git commit -m "feat(individual-packing): add fee and product attribute GraphQL resolvers"
```

---

## Task 13: setup:upgrade + di:compile + cache:flush

- [ ] **Step 1: Run setup:upgrade (applies db_schema.xml column + Data Patch)**

```bash
php -d memory_limit=-1 bin/magento setup:upgrade
```

Expected output includes:
- `Local_IndividualPacking` module listed under installed modules
- Schema patches applied (new column on `quote_item`)
- Data patches applied (`AddIndividuallyPackableAttribute`)

- [ ] **Step 2: Compile DI**

```bash
php -d memory_limit=-1 bin/magento setup:di:compile
```

Expected: `Generated code successfully.` — zero compilation errors.

- [ ] **Step 3: Flush caches**

```bash
bin/magento cache:flush
```

Expected: `Flushed cache types: config block_html full_page translate`

- [ ] **Step 4: Verify column exists**

```bash
bin/magento dev:db:status 2>/dev/null || \
  mysql -u magento -pmagento magento -e "SHOW COLUMNS FROM quote_item LIKE 'individual_packing_selected';"
```

Expected: row showing `individual_packing_selected` with type `smallint`.

- [ ] **Step 5: Commit**

```bash
git add app/etc/config.php
git commit -m "feat(individual-packing): run setup:upgrade — module enabled, schema + data patches applied"
```

---

## Task 14: Manual end-to-end verification

All steps below use the Magento admin at `/admin` (credentials: `admin` / `password1`) and a GraphQL client pointed at `http://localhost:8080/graphql`.

- [ ] **Step 1: Verify attribute in Admin**

Navigate to **Admin → Catalog → Products → (any product) → Edit**.
In the "Attributes" or default group, find the **Individual Packing** dropdown.
Expected: dropdown with options `eligible`, `pre_packaged`, `boxed`, `not_eligible`.

Set one product to `eligible` and save.

- [ ] **Step 2: Verify attribute via GraphQL**

```graphql
{
  products(filter: { sku: { eq: "YOUR_SKU" } }) {
    items {
      sku
      individually_packable
    }
  }
}
```

Expected: `"individually_packable": "eligible"`

- [ ] **Step 3: Create a cart and add the product**

```graphql
mutation {
  createEmptyCart
}
```

Note the masked cart ID. Then:

```graphql
mutation AddToCart($cartId: String!, $sku: String!) {
  addSimpleProductsToCart(
    input: { cart_id: $cartId, cart_items: [{ data: { sku: $sku, quantity: 2 } }] }
  ) {
    cart { items { id quantity } }
  }
}
```

Note the numeric `id` from the response — this is the `cart_item_id`.

- [ ] **Step 4: Select individual packing**

```graphql
mutation {
  setIndividualPackingOnCartItem(
    input: {
      cart_id: "YOUR_MASKED_CART_ID"
      cart_item_id: YOUR_ITEM_ID
      individual_packing: true
    }
  ) {
    cart {
      individual_packing_fee
      individual_packing_fee_formatted
      items {
        id
        quantity
        individual_packing_selected
      }
    }
  }
}
```

Expected:
- `individual_packing_fee`: `0.98` (2 × £0.49)
- `individual_packing_fee_formatted`: `"£0.98"`
- `individual_packing_selected`: `true`

- [ ] **Step 5: Deselect and verify fee drops**

```graphql
mutation {
  setIndividualPackingOnCartItem(
    input: {
      cart_id: "YOUR_MASKED_CART_ID"
      cart_item_id: YOUR_ITEM_ID
      individual_packing: false
    }
  ) {
    cart {
      individual_packing_fee
      items { individual_packing_selected }
    }
  }
}
```

Expected: `individual_packing_fee: 0`, `individual_packing_selected: false`

- [ ] **Step 6: Place an order and verify Admin order view**

Complete checkout (add shipping address, select payment method, place order).
Navigate to **Admin → Sales → Orders → (new order) → Items Ordered tab**.

Expected: each item with individual packing selected shows:
```
Individual Packing     Yes — each item bagged and labelled separately
Individual Packing Fee £0.98 (£0.49 × 2)
```

- [ ] **Step 7: Verify customer account order history**

Log in as the customer → **My Account → My Orders → View Order → Items Ordered**.
Same `additional_options` should appear.

- [ ] **Step 8: Verify order email**

Check Mailpit at `http://localhost:8025` for the order confirmation email.
The items table in the email should include the Individual Packing option rows.

- [ ] **Step 9: Final commit tag**

```bash
git tag -a v0.1-individual-packing -m "Individual Packing feature complete"
```
