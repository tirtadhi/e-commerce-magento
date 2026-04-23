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
    echo "Pasaria website not found." . PHP_EOL;
    exit(1);
}

echo "website_id=" . $websiteId . PHP_EOL;

$rows = $conn->fetchAll(
    "SELECT cpe.sku, cpw.website_id
     FROM catalog_product_entity cpe
     INNER JOIN catalog_product_website cpw ON cpw.product_id = cpe.entity_id
     WHERE cpw.website_id = :website_id
     ORDER BY cpe.entity_id ASC",
    ['website_id' => $websiteId]
);

echo "product_count=" . count($rows) . PHP_EOL;
foreach ($rows as $row) {
    echo $row['sku'] . PHP_EOL;
}
