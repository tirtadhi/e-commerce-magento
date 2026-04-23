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

$websiteId = (int) $conn->fetchOne("SELECT website_id FROM store_website WHERE code = 'pasaria' LIMIT 1");
if ($websiteId <= 0) {
    throw new RuntimeException('Pasaria website not found.');
}

$groupId = (int) $conn->fetchOne(
    'SELECT default_group_id FROM store_website WHERE website_id = :website_id',
    ['website_id' => $websiteId]
);
$rootCategoryId = (int) $conn->fetchOne(
    'SELECT root_category_id FROM store_group WHERE group_id = :group_id',
    ['group_id' => $groupId]
);

if ($rootCategoryId <= 1) {
    throw new RuntimeException('Invalid Pasaria root category.');
}

$nameAttributeIds = $conn->fetchCol(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id IN (
        SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = 'catalog_category'
    )"
);
$nameAttrIn = empty($nameAttributeIds) ? '0' : implode(',', array_map('intval', $nameAttributeIds));

$children = $conn->fetchAll(
    "SELECT cce.entity_id, cce.path, MAX(ccev.value) AS name
     FROM catalog_category_entity cce
     LEFT JOIN catalog_category_entity_varchar ccev
       ON ccev.entity_id = cce.entity_id
      AND ccev.store_id = 0
      AND ccev.attribute_id IN ({$nameAttrIn})
     WHERE cce.parent_id = :parent_id
     GROUP BY cce.entity_id, cce.path
     ORDER BY cce.entity_id ASC",
    ['parent_id' => $rootCategoryId]
);

if (empty($children)) {
    echo 'No child categories under Pasaria root.' . PHP_EOL;
    exit(0);
}

$keepId = 0;
foreach ($children as $child) {
    $id = (int) $child['entity_id'];
    $name = (string) ($child['name'] ?? '');
    $path = (string) $child['path'];
    if ($name === 'Pasaria Collection' && $path === ('1/' . $rootCategoryId . '/' . $id)) {
        $keepId = max($keepId, $id);
    }
}
if ($keepId === 0) {
    foreach ($children as $child) {
        $id = (int) $child['entity_id'];
        $path = (string) $child['path'];
        if ($path === ('1/' . $rootCategoryId . '/' . $id)) {
            $keepId = max($keepId, $id);
        }
    }
}
if ($keepId === 0) {
    $last = end($children);
    $keepId = (int) $last['entity_id'];
}

$legacyIds = [];
foreach ($children as $child) {
    $id = (int) $child['entity_id'];
    if ($id !== $keepId) {
        $legacyIds[] = $id;
    }
}

if (empty($legacyIds)) {
    echo 'No legacy categories to purge. keep_id=' . $keepId . PHP_EOL;
    exit(0);
}

$legacyIn = implode(',', array_map('intval', $legacyIds));

echo 'keep_id=' . $keepId . PHP_EOL;
echo 'legacy_ids=' . implode(',', $legacyIds) . PHP_EOL;

$conn->beginTransaction();

try {
    // Move product assignments from legacy categories to kept category.
    $productIds = $conn->fetchCol(
        "SELECT DISTINCT product_id FROM catalog_category_product WHERE category_id IN ({$legacyIn})"
    );

    foreach ($productIds as $productIdRaw) {
        $productId = (int) $productIdRaw;
        $exists = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM catalog_category_product WHERE category_id = :category_id AND product_id = :product_id',
            ['category_id' => $keepId, 'product_id' => $productId]
        );
        if ($exists <= 0) {
            $maxPos = (int) $conn->fetchOne(
                'SELECT COALESCE(MAX(position), 0) FROM catalog_category_product WHERE category_id = :category_id',
                ['category_id' => $keepId]
            );
            $conn->insert('catalog_category_product', [
                'category_id' => $keepId,
                'product_id' => $productId,
                'position' => $maxPos + 1,
            ]);
        }
    }

    // Remove legacy category product links.
    $conn->query("DELETE FROM catalog_category_product WHERE category_id IN ({$legacyIn})");

    // Remove related URL rewrites.
    $conn->query(
        "DELETE FROM url_rewrite
         WHERE (entity_type = 'category' AND entity_id IN ({$legacyIn}))
            OR metadata LIKE '%\\\"category_id\\\":\\\"" . (int) $legacyIds[0] . "\\\"%'
            OR metadata LIKE '%\\\"category_id\\\":" . (int) $legacyIds[0] . "%'"
    );
    foreach ($legacyIds as $legacyId) {
        $conn->query(
            "DELETE FROM url_rewrite
             WHERE metadata LIKE '%\\\"category_id\\\":\\\"{$legacyId}\\\"%'
                OR metadata LIKE '%\\\"category_id\\\":{$legacyId}%'"
        );
    }

    // Purge EAV rows then base entity rows.
    $conn->query("DELETE FROM catalog_category_entity_varchar WHERE entity_id IN ({$legacyIn})");
    $conn->query("DELETE FROM catalog_category_entity_int WHERE entity_id IN ({$legacyIn})");
    $conn->query("DELETE FROM catalog_category_entity_text WHERE entity_id IN ({$legacyIn})");
    $conn->query("DELETE FROM catalog_category_entity_decimal WHERE entity_id IN ({$legacyIn})");
    $conn->query("DELETE FROM catalog_category_entity_datetime WHERE entity_id IN ({$legacyIn})");
    $conn->query("DELETE FROM catalog_category_entity WHERE entity_id IN ({$legacyIn})");

    $conn->commit();
    echo 'Legacy category purge completed.' . PHP_EOL;
} catch (Throwable $e) {
    $conn->rollBack();
    throw $e;
}
