<?php

declare(strict_types=1);

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\GroupRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;

require __DIR__ . '/../../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

/** @var State $state */
$state = $objectManager->get(State::class);
try {
    $state->setAreaCode('adminhtml');
} catch (Throwable $e) {
    // Area code may already be set when script is rerun.
}

/** @var WebsiteRepositoryInterface $websiteRepository */
$websiteRepository = $objectManager->get(WebsiteRepositoryInterface::class);
/** @var GroupRepositoryInterface $groupRepository */
$groupRepository = $objectManager->get(GroupRepositoryInterface::class);
/** @var CategoryRepositoryInterface $categoryRepository */
$categoryRepository = $objectManager->get(CategoryRepositoryInterface::class);
/** @var CategoryFactory $categoryFactory */
$categoryFactory = $objectManager->get(CategoryFactory::class);
/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
/** @var StockRegistryInterface $stockRegistry */
$stockRegistry = $objectManager->get(StockRegistryInterface::class);
/** @var ResourceConnection $resource */
$resource = $objectManager->get(ResourceConnection::class);

$website = $websiteRepository->get('pasaria');
$storeGroup = $groupRepository->get((int) $website->getDefaultGroupId());
$rootCategoryId = (int) $storeGroup->getRootCategoryId();

if ($rootCategoryId <= 1) {
    throw new RuntimeException('Pasaria store group root category belum valid. Set root category dulu di admin.');
}

$rootCategory = $categoryRepository->get($rootCategoryId, 0);

echo "Pasaria website ID: " . $website->getId() . PHP_EOL;
echo "Pasaria root category ID: " . $rootCategoryId . " (" . $rootCategory->getName() . ")" . PHP_EOL;

$connection = $resource->getConnection();
$pasariaCategoryId = 0;

$nameAttributeIds = $connection->fetchCol(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id IN (
        SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = 'catalog_category'
    )"
);

if (!empty($nameAttributeIds)) {
    $nameAttributeIn = implode(',', array_map('intval', $nameAttributeIds));
    $pasariaCategoryId = (int) $connection->fetchOne(
        "SELECT cce.entity_id
         FROM catalog_category_entity cce
         INNER JOIN catalog_category_entity_varchar ccev
           ON ccev.entity_id = cce.entity_id
          AND ccev.store_id = 0
          AND ccev.attribute_id IN ({$nameAttributeIn})
          AND ccev.value = 'Pasaria Collection'
         WHERE cce.parent_id = :parent_id
         ORDER BY cce.entity_id DESC
         LIMIT 1",
        ['parent_id' => $rootCategoryId]
    );
}

$childIds = array_values(array_filter(array_map('intval', explode(',', (string) $rootCategory->getChildren()))));
if ($pasariaCategoryId === 0 && !empty($childIds)) {
    $pasariaCategoryId = (int) $childIds[0];
}

if ($pasariaCategoryId === 0) {
    $newCategory = $categoryFactory->create();
    $newCategory->setName('Pasaria Collection');
    $newCategory->setUrlKey('pasaria-collection-' . date('YmdHis'));
    $newCategory->setIsActive(true);
    $newCategory->setParentId($rootCategoryId);
    $newCategory->setPath((string) $rootCategory->getPath());
    $newCategory->setIncludeInMenu(true);
    $newCategory->setIsAnchor(true);
    $newCategory->setDisplayMode('PRODUCTS');

    try {
        $savedCategory = $categoryRepository->save($newCategory);
        $pasariaCategoryId = (int) $savedCategory->getId();
        echo "Created Pasaria child category ID: " . $pasariaCategoryId . PHP_EOL;
    } catch (Throwable $e) {
        // Keep seeding resilient even if category creation hits a rewrite conflict.
        $pasariaCategoryId = $rootCategoryId;
        echo "Failed creating child category, fallback to root category assignment." . PHP_EOL;
    }
}

$pasariaCategoryId = $pasariaCategoryId > 0 ? $pasariaCategoryId : $rootCategoryId;

if ($pasariaCategoryId !== $rootCategoryId) {
    $expectedPath = trim((string) $rootCategory->getPath(), '/') . '/' . $pasariaCategoryId;
    $expectedLevel = count(explode('/', $expectedPath)) - 1;
    $maxSiblingPosition = (int) $connection->fetchOne(
        'SELECT COALESCE(MAX(position), 0) FROM catalog_category_entity WHERE parent_id = :parent_id',
        ['parent_id' => $rootCategoryId]
    );

    $connection->update(
        'catalog_category_entity',
        [
            'parent_id' => $rootCategoryId,
            'path' => $expectedPath,
            'level' => $expectedLevel,
            'position' => max(1, $maxSiblingPosition),
        ],
        ['entity_id = ?' => $pasariaCategoryId]
    );
}

$pasariaCategory = $categoryRepository->get($pasariaCategoryId, 0);

echo "Using category ID for product assignment: " . $pasariaCategoryId . PHP_EOL;

$defaultAttributeSetId = (int) $connection->fetchOne(
    "SELECT eas.attribute_set_id
     FROM eav_attribute_set eas
     INNER JOIN eav_entity_type eet ON eet.entity_type_id = eas.entity_type_id
     WHERE eet.entity_type_code = 'catalog_product'
     ORDER BY (eas.attribute_set_name = 'Default') DESC, eas.attribute_set_id ASC
     LIMIT 1"
);

if ($defaultAttributeSetId <= 0) {
    throw new RuntimeException('Default attribute set untuk catalog_product tidak ditemukan.');
}

$connection->insertOnDuplicate(
    'cataloginventory_stock',
    [
        'stock_id' => 1,
        'website_id' => 0,
        'stock_name' => 'Default',
    ],
    ['website_id', 'stock_name']
);

$productsToSeed = [
    [
        'sku' => 'PASARIA-TSHIRT-RED-M',
        'name' => 'Pasaria T-Shirt Red M',
        'price' => 159000,
        'qty' => 50,
    ],
    [
        'sku' => 'PASARIA-TOTE-BLACK',
        'name' => 'Pasaria Tote Bag Black',
        'price' => 89000,
        'qty' => 80,
    ],
    [
        'sku' => 'PASARIA-MUG-WHITE',
        'name' => 'Pasaria Mug White',
        'price' => 69000,
        'qty' => 120,
    ],
    [
        'sku' => 'PASARIA-HOODIE-GRAY-L',
        'name' => 'Pasaria Hoodie Gray L',
        'price' => 289000,
        'qty' => 30,
    ],
    [
        'sku' => 'PASARIA-CAP-NAVY',
        'name' => 'Pasaria Cap Navy',
        'price' => 99000,
        'qty' => 70,
    ],
    [
        'sku' => 'PASARIA-BOTTLE-600ML',
        'name' => 'Pasaria Bottle 600ml',
        'price' => 119000,
        'qty' => 65,
    ],
    [
        'sku' => 'PASARIA-NOTEBOOK-A5',
        'name' => 'Pasaria Notebook A5',
        'price' => 49000,
        'qty' => 150,
    ],
    [
        'sku' => 'PASARIA-KEYCHAIN-METAL',
        'name' => 'Pasaria Keychain Metal',
        'price' => 39000,
        'qty' => 200,
    ],
    [
        'sku' => 'PASARIA-LANYARD-BLACK',
        'name' => 'Pasaria Lanyard Black',
        'price' => 35000,
        'qty' => 180,
    ],
    [
        'sku' => 'PASARIA-STICKER-PACK',
        'name' => 'Pasaria Sticker Pack',
        'price' => 25000,
        'qty' => 250,
    ],
    [
        'sku' => 'PASARIA-POSTER-A3',
        'name' => 'Pasaria Poster A3',
        'price' => 45000,
        'qty' => 90,
    ],
    [
        'sku' => 'PASARIA-SOCKS-WHITE',
        'name' => 'Pasaria Socks White',
        'price' => 59000,
        'qty' => 110,
    ],
    [
        'sku' => 'PASARIA-BACKPACK-URBAN',
        'name' => 'Pasaria Backpack Urban',
        'price' => 329000,
        'qty' => 25,
    ],
    [
        'sku' => 'PASARIA-DESKMAT-XL',
        'name' => 'Pasaria Deskmat XL',
        'price' => 149000,
        'qty' => 45,
    ],
];

foreach ($productsToSeed as $seed) {
    try {
        $product = $productRepository->get($seed['sku'], false, null, true);
        echo "Product exists: " . $seed['sku'] . PHP_EOL;
    } catch (NoSuchEntityException $e) {
        /** @var ProductInterface|\Magento\Catalog\Model\Product $product */
        $product = $objectManager->create(\Magento\Catalog\Model\Product::class);
        $product->setSku($seed['sku']);
        $product->setName($seed['name']);
        $product->setAttributeSetId($defaultAttributeSetId);
        $product->setStatus(Status::STATUS_ENABLED);
        $product->setVisibility(Visibility::VISIBILITY_BOTH);
        $product->setTypeId(Type::TYPE_SIMPLE);
        $product->setPrice((float) $seed['price']);
        $product->setTaxClassId(0);
        $product->setWebsiteIds([(int) $website->getId()]);
        $product->setCategoryIds([(int) $pasariaCategory->getId()]);
        $product->setUrlKey(strtolower(str_replace('_', '-', preg_replace('/[^a-z0-9_]+/i', '-', $seed['sku']))));
        $product->setStockData([
            'use_config_manage_stock' => 1,
            'is_in_stock' => 1,
            'qty' => (float) $seed['qty'],
        ]);

        $productRepository->save($product);
        echo "Created product: " . $seed['sku'] . PHP_EOL;
    }

    $loaded = $productRepository->get($seed['sku'], false, null, true);
    $websiteIds = array_unique(array_merge($loaded->getWebsiteIds(), [(int) $website->getId()]));
    $categoryIds = array_unique(array_merge($loaded->getCategoryIds(), [(int) $pasariaCategory->getId()]));
    $loaded->setWebsiteIds($websiteIds);
    $loaded->setCategoryIds($categoryIds);
    $productRepository->save($loaded);

    $stockItem = $stockRegistry->getStockItemBySku($seed['sku']);
    $stockItem->setQty((float) $seed['qty']);
    $stockItem->setIsInStock(true);
    $stockRegistry->updateStockItemBySku($seed['sku'], $stockItem);

    echo "Ensured assignment and stock for: " . $seed['sku'] . PHP_EOL;
}

echo 'Pasaria catalog seed completed.' . PHP_EOL;
