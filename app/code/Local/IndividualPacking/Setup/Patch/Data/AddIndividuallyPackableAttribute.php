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
