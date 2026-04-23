<?php

declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;

require __DIR__ . '/../../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();

/** @var ResourceConnection $resource */
$resource = $om->get(ResourceConnection::class);
$conn = $resource->getConnection();

$conn->beginTransaction();

try {
    $entityTypes = $conn->fetchAll(
        "SELECT entity_type_id, entity_type_code FROM eav_entity_type
         WHERE entity_type_code IN ('catalog_product', 'catalog_category', 'customer', 'customer_address')
         ORDER BY entity_type_id"
    );

    foreach ($entityTypes as $entityType) {
        $entityTypeId = (int) $entityType['entity_type_id'];
        $entityTypeCode = (string) $entityType['entity_type_code'];

        $attributeSetId = (int) $conn->fetchOne(
            "SELECT attribute_set_id FROM eav_attribute_set
             WHERE entity_type_id = :entity_type_id
               AND attribute_set_name = 'Default'
             ORDER BY attribute_set_id ASC
             LIMIT 1",
            ['entity_type_id' => $entityTypeId]
        );

        if ($attributeSetId <= 0) {
            $conn->insert('eav_attribute_set', [
                'entity_type_id' => $entityTypeId,
                'attribute_set_name' => 'Default',
                'sort_order' => 1,
            ]);
            $attributeSetId = (int) $conn->lastInsertId('eav_attribute_set');
            echo "Created attribute set Default for {$entityTypeCode} (entity_type_id={$entityTypeId}, set_id={$attributeSetId})" . PHP_EOL;
        } else {
            echo "Attribute set Default exists for {$entityTypeCode} (entity_type_id={$entityTypeId}, set_id={$attributeSetId})" . PHP_EOL;
        }

        $conn->update('eav_entity_type', ['default_attribute_set_id' => $attributeSetId], ['entity_type_id = ?' => $entityTypeId]);

        $attributeGroupId = (int) $conn->fetchOne(
            "SELECT attribute_group_id FROM eav_attribute_group
             WHERE attribute_set_id = :attribute_set_id
               AND attribute_group_name = 'General'
             ORDER BY attribute_group_id ASC
             LIMIT 1",
            ['attribute_set_id' => $attributeSetId]
        );

        if ($attributeGroupId <= 0) {
            $conn->insert('eav_attribute_group', [
                'attribute_set_id' => $attributeSetId,
                'attribute_group_name' => 'General',
                'sort_order' => 1,
                'default_id' => 1,
                'attribute_group_code' => 'general',
            ]);
            $attributeGroupId = (int) $conn->lastInsertId('eav_attribute_group');
            echo "Created attribute group General for set_id={$attributeSetId} (group_id={$attributeGroupId})" . PHP_EOL;
        }

        $attributeIds = $conn->fetchCol(
            "SELECT attribute_id
             FROM eav_attribute
             WHERE entity_type_id = :entity_type_id
             ORDER BY attribute_id ASC",
            ['entity_type_id' => $entityTypeId]
        );

        $sortOrder = 1;
        foreach ($attributeIds as $attributeIdRaw) {
            $attributeId = (int) $attributeIdRaw;
            $exists = (int) $conn->fetchOne(
                "SELECT entity_attribute_id
                 FROM eav_entity_attribute
                 WHERE entity_type_id = :entity_type_id
                   AND attribute_set_id = :attribute_set_id
                   AND attribute_group_id = :attribute_group_id
                   AND attribute_id = :attribute_id
                 LIMIT 1",
                [
                    'entity_type_id' => $entityTypeId,
                    'attribute_set_id' => $attributeSetId,
                    'attribute_group_id' => $attributeGroupId,
                    'attribute_id' => $attributeId,
                ]
            );

            if ($exists <= 0) {
                $conn->insert('eav_entity_attribute', [
                    'entity_type_id' => $entityTypeId,
                    'attribute_set_id' => $attributeSetId,
                    'attribute_group_id' => $attributeGroupId,
                    'attribute_id' => $attributeId,
                    'sort_order' => $sortOrder,
                ]);
            }

            $sortOrder++;
        }

        echo "Ensured attribute mapping for {$entityTypeCode} (entity_type_id={$entityTypeId}, attributes=" . count($attributeIds) . ")" . PHP_EOL;
    }

    $conn->commit();
    echo 'EAV repair completed.' . PHP_EOL;
} catch (Throwable $e) {
    $conn->rollBack();
    throw $e;
}
