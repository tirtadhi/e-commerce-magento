<?php

declare(strict_types=1);

use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleDataSetupInterface;

require __DIR__ . '/../../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();

/** @var ModuleDataSetupInterface $moduleDataSetup */
$moduleDataSetup = $om->get(ModuleDataSetupInterface::class);
/** @var CategorySetupFactory $categorySetupFactory */
$categorySetupFactory = $om->get(CategorySetupFactory::class);
/** @var ResourceConnection $resource */
$resource = $om->get(ResourceConnection::class);

$categorySetup = $categorySetupFactory->create(['setup' => $moduleDataSetup]);
$defaults = $categorySetup->getDefaultEntities();

$entityCodes = ['catalog_product', 'catalog_category'];

foreach ($entityCodes as $entityCode) {
    if (!isset($defaults[$entityCode]['attributes'])) {
        continue;
    }

    foreach ($defaults[$entityCode]['attributes'] as $attributeCode => $definition) {
        $attributeId = (int) $categorySetup->getAttributeId($entityCode, $attributeCode);
        if ($attributeId > 0) {
            continue;
        }

        $categorySetup->addAttribute($entityCode, $attributeCode, $definition);
        echo "Added {$entityCode}.{$attributeCode}" . PHP_EOL;
    }
}

$explicitAttributes = [
    'catalog_product' => [
        'url_key' => [
            'type' => 'varchar',
            'label' => 'URL Key',
            'input' => 'text',
            'required' => false,
            'sort_order' => 31,
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            'group' => 'Search Engine Optimization',
            'is_visible' => true,
            'is_html_allowed_on_front' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'unique' => false,
        ],
        'url_path' => [
            'type' => 'varchar',
            'label' => 'URL Path',
            'input' => 'text',
            'required' => false,
            'sort_order' => 32,
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            'group' => 'Search Engine Optimization',
            'is_visible' => false,
            'visible' => false,
            'is_html_allowed_on_front' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'unique' => false,
        ],
    ],
    'catalog_category' => [
        'url_key' => [
            'type' => 'varchar',
            'label' => 'URL Key',
            'input' => 'text',
            'required' => false,
            'sort_order' => 31,
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            'group' => 'Search Engine Optimization',
            'is_visible' => true,
            'is_html_allowed_on_front' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'unique' => false,
        ],
        'url_path' => [
            'type' => 'varchar',
            'label' => 'URL Path',
            'input' => 'text',
            'required' => false,
            'sort_order' => 32,
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            'group' => 'Search Engine Optimization',
            'is_visible' => false,
            'visible' => false,
            'is_html_allowed_on_front' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'unique' => false,
        ],
    ],
];

foreach ($explicitAttributes as $entityCode => $attrs) {
    foreach ($attrs as $attributeCode => $definition) {
        $attributeId = (int) $categorySetup->getAttributeId($entityCode, $attributeCode);
        if ($attributeId > 0) {
            continue;
        }

        $categorySetup->addAttribute($entityCode, $attributeCode, $definition);
        echo "Added explicit {$entityCode}.{$attributeCode}" . PHP_EOL;
    }
}

$conn = $resource->getConnection();

// This database has duplicate entity type rows for catalog entities.
// Mirror attributes from the first entity_type_id to duplicates so runtime lookups work consistently.
foreach (['catalog_product', 'catalog_category'] as $entityCode) {
    $typeIds = array_map(
        'intval',
        $conn->fetchCol(
            'SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = :code ORDER BY entity_type_id ASC',
            ['code' => $entityCode]
        )
    );

    if (count($typeIds) < 2) {
        continue;
    }

    $primaryTypeId = $typeIds[0];
    $duplicateTypeIds = array_slice($typeIds, 1);

    $sourceAttributes = $conn->fetchAll(
        'SELECT * FROM eav_attribute WHERE entity_type_id = :entity_type_id ORDER BY attribute_id ASC',
        ['entity_type_id' => $primaryTypeId]
    );

    foreach ($duplicateTypeIds as $duplicateTypeId) {
        $defaultSetId = (int) $conn->fetchOne(
            'SELECT default_attribute_set_id FROM eav_entity_type WHERE entity_type_id = :entity_type_id',
            ['entity_type_id' => $duplicateTypeId]
        );
        $groupId = (int) $conn->fetchOne(
            'SELECT attribute_group_id FROM eav_attribute_group WHERE attribute_set_id = :set_id ORDER BY attribute_group_id ASC LIMIT 1',
            ['set_id' => $defaultSetId]
        );

        foreach ($sourceAttributes as $sourceAttribute) {
            $attributeCode = (string) $sourceAttribute['attribute_code'];
            $existingId = (int) $conn->fetchOne(
                'SELECT attribute_id FROM eav_attribute WHERE entity_type_id = :entity_type_id AND attribute_code = :attribute_code LIMIT 1',
                [
                    'entity_type_id' => $duplicateTypeId,
                    'attribute_code' => $attributeCode,
                ]
            );

            if ($existingId > 0) {
                continue;
            }

            $row = $sourceAttribute;
            unset($row['attribute_id']);
            $row['entity_type_id'] = $duplicateTypeId;
            $conn->insert('eav_attribute', $row);
            $newAttributeId = (int) $conn->lastInsertId('eav_attribute');

            $catalogMeta = $conn->fetchRow(
                'SELECT * FROM catalog_eav_attribute WHERE attribute_id = :attribute_id LIMIT 1',
                ['attribute_id' => (int) $sourceAttribute['attribute_id']]
            );
            if ($catalogMeta) {
                $catalogRow = $catalogMeta;
                $catalogRow['attribute_id'] = $newAttributeId;
                $conn->insert('catalog_eav_attribute', $catalogRow);
            }

            if ($defaultSetId > 0 && $groupId > 0) {
                $conn->insertOnDuplicate(
                    'eav_entity_attribute',
                    [
                        'entity_type_id' => $duplicateTypeId,
                        'attribute_set_id' => $defaultSetId,
                        'attribute_group_id' => $groupId,
                        'attribute_id' => $newAttributeId,
                        'sort_order' => (int) $sourceAttribute['sort_order'],
                    ],
                    ['sort_order', 'attribute_group_id', 'entity_type_id']
                );
            }

            echo "Mirrored {$entityCode}.{$attributeCode} to entity_type_id={$duplicateTypeId}" . PHP_EOL;
        }
    }
}

echo 'Catalog default attributes restore completed.' . PHP_EOL;
