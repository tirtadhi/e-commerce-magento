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
$groupId = (int) $conn->fetchOne('SELECT default_group_id FROM store_website WHERE website_id = :website_id', ['website_id' => $websiteId]);
$rootCategoryId = (int) $conn->fetchOne('SELECT root_category_id FROM store_group WHERE group_id = :group_id', ['group_id' => $groupId]);

$children = $conn->fetchAll(
    'SELECT entity_id, path FROM catalog_category_entity WHERE parent_id = :parent_id ORDER BY entity_id ASC',
    ['parent_id' => $rootCategoryId]
);

if (empty($children)) {
    echo 'No Pasaria child categories found.' . PHP_EOL;
    exit(0);
}

$keepId = 0;
foreach ($children as $child) {
    $entityId = (int) $child['entity_id'];
    $path = (string) $child['path'];
    if ($path === ('1/' . $rootCategoryId . '/' . $entityId)) {
        $keepId = max($keepId, $entityId);
    }
}

if ($keepId === 0) {
    $last = end($children);
    $keepId = (int) $last['entity_id'];
}

$attrRows = $conn->fetchAll(
    "SELECT ea.attribute_id, ea.attribute_code
     FROM eav_attribute ea
     WHERE ea.attribute_code IN ('is_active', 'include_in_menu')
       AND ea.entity_type_id IN (
          SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = 'catalog_category'
       )"
);

$attrIds = [];
foreach ($attrRows as $attrRow) {
    $attrIds[(string) $attrRow['attribute_code']] = (int) $attrRow['attribute_id'];
}

if (!isset($attrIds['is_active'], $attrIds['include_in_menu'])) {
    throw new RuntimeException('Category attributes is_active/include_in_menu not found.');
}

foreach ($children as $child) {
    $entityId = (int) $child['entity_id'];
    $isKeep = $entityId === $keepId;

    $conn->insertOnDuplicate(
        'catalog_category_entity_int',
        [
            'attribute_id' => $attrIds['is_active'],
            'store_id' => 0,
            'entity_id' => $entityId,
            'value' => $isKeep ? 1 : 0,
        ],
        ['value']
    );

    $conn->insertOnDuplicate(
        'catalog_category_entity_int',
        [
            'attribute_id' => $attrIds['include_in_menu'],
            'store_id' => 0,
            'entity_id' => $entityId,
            'value' => $isKeep ? 1 : 0,
        ],
        ['value']
    );

    echo ($isKeep ? 'KEEP ' : 'DISABLE ') . $entityId . ' path=' . $child['path'] . PHP_EOL;
}

echo 'Pasaria category cleanup completed. keep_id=' . $keepId . PHP_EOL;
