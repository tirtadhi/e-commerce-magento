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
$groupId = (int) $conn->fetchOne("SELECT default_group_id FROM store_website WHERE website_id = :website_id", ['website_id' => $websiteId]);
$rootCategoryId = (int) $conn->fetchOne("SELECT root_category_id FROM store_group WHERE group_id = :group_id", ['group_id' => $groupId]);

$nameAttributeIds = $conn->fetchCol(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id IN (
        SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = 'catalog_category'
    )"
);

if (empty($nameAttributeIds)) {
    echo "No category name attribute found" . PHP_EOL;
    exit(1);
}

$in = implode(',', array_map('intval', $nameAttributeIds));
$rows = $conn->fetchAll(
    "SELECT cce.entity_id, cce.parent_id, cce.path, MAX(ccev.value) AS name
     FROM catalog_category_entity cce
     LEFT JOIN catalog_category_entity_varchar ccev
       ON ccev.entity_id = cce.entity_id
      AND ccev.store_id = 0
      AND ccev.attribute_id IN ({$in})
     WHERE cce.parent_id = :root_category_id
     GROUP BY cce.entity_id, cce.parent_id, cce.path
     ORDER BY cce.entity_id ASC",
    ['root_category_id' => $rootCategoryId]
);

echo 'root_category_id=' . $rootCategoryId . PHP_EOL;
echo 'child_count=' . count($rows) . PHP_EOL;
foreach ($rows as $row) {
    echo 'category_id=' . $row['entity_id'] . ';name=' . ($row['name'] ?? '') . ';path=' . $row['path'] . PHP_EOL;
}
