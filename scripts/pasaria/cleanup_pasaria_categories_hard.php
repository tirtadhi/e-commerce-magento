<?php

declare(strict_types=1);

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Store\Api\GroupRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;

require __DIR__ . '/../../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();

/** @var State $state */
$state = $om->get(State::class);
try {
    $state->setAreaCode('adminhtml');
} catch (Throwable $e) {
}

/** @var WebsiteRepositoryInterface $websiteRepository */
$websiteRepository = $om->get(WebsiteRepositoryInterface::class);
/** @var GroupRepositoryInterface $groupRepository */
$groupRepository = $om->get(GroupRepositoryInterface::class);
/** @var CategoryRepositoryInterface $categoryRepository */
$categoryRepository = $om->get(CategoryRepositoryInterface::class);
/** @var ResourceConnection $resource */
$resource = $om->get(ResourceConnection::class);
$conn = $resource->getConnection();

$website = $websiteRepository->get('pasaria');
$group = $groupRepository->get((int) $website->getDefaultGroupId());
$rootCategoryId = (int) $group->getRootCategoryId();

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
    echo 'No child categories found under Pasaria root.' . PHP_EOL;
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

echo 'Keeping category ID: ' . $keepId . PHP_EOL;

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

foreach ($children as $child) {
    $id = (int) $child['entity_id'];
    if ($id === $keepId) {
        continue;
    }

    try {
        $category = $categoryRepository->get($id, 0);
        $categoryRepository->delete($category);
        echo 'DELETED ' . $id . PHP_EOL;
    } catch (Throwable $e) {
        if (isset($attrIds['is_active'])) {
            $conn->insertOnDuplicate(
                'catalog_category_entity_int',
                [
                    'attribute_id' => $attrIds['is_active'],
                    'store_id' => 0,
                    'entity_id' => $id,
                    'value' => 0,
                ],
                ['value']
            );
        }
        if (isset($attrIds['include_in_menu'])) {
            $conn->insertOnDuplicate(
                'catalog_category_entity_int',
                [
                    'attribute_id' => $attrIds['include_in_menu'],
                    'store_id' => 0,
                    'entity_id' => $id,
                    'value' => 0,
                ],
                ['value']
            );
        }
        echo 'DISABLED ' . $id . ' (delete failed: ' . $e->getMessage() . ')' . PHP_EOL;
    }
}

echo 'Pasaria hard category cleanup completed.' . PHP_EOL;
