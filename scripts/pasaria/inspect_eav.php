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

echo "== eav_entity_type ==" . PHP_EOL;
$rows = $conn->fetchAll(
    'SELECT entity_type_id, entity_type_code, default_attribute_set_id, additional_attribute_table FROM eav_entity_type ORDER BY entity_type_id'
);
foreach ($rows as $row) {
    echo implode('|', [
        $row['entity_type_id'],
        $row['entity_type_code'],
        $row['default_attribute_set_id'],
        (string) $row['additional_attribute_table'],
    ]) . PHP_EOL;
}

echo PHP_EOL . "== eav_attribute_set count ==" . PHP_EOL;
$rows = $conn->fetchAll('SELECT entity_type_id, COUNT(*) cnt FROM eav_attribute_set GROUP BY entity_type_id ORDER BY entity_type_id');
foreach ($rows as $row) {
    echo $row['entity_type_id'] . '|' . $row['cnt'] . PHP_EOL;
}

echo PHP_EOL . "== eav_attribute_group count ==" . PHP_EOL;
$rows = $conn->fetchAll('SELECT attribute_set_id, COUNT(*) cnt FROM eav_attribute_group GROUP BY attribute_set_id ORDER BY attribute_set_id');
foreach ($rows as $row) {
    echo $row['attribute_set_id'] . '|' . $row['cnt'] . PHP_EOL;
}

echo PHP_EOL . "== catalog_product attribute codes ==" . PHP_EOL;
$productTypeIds = $conn->fetchCol("SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = 'catalog_product' ORDER BY entity_type_id");
foreach ($productTypeIds as $typeIdRaw) {
    $typeId = (int) $typeIdRaw;
    echo "entity_type_id={$typeId}" . PHP_EOL;
    $codes = $conn->fetchCol(
        'SELECT attribute_code FROM eav_attribute WHERE entity_type_id = :entity_type_id ORDER BY attribute_code ASC',
        ['entity_type_id' => $typeId]
    );
    foreach ($codes as $code) {
        echo ' - ' . $code . PHP_EOL;
    }
}

echo PHP_EOL . "== eav_attribute by entity_type ==" . PHP_EOL;
$rows = $conn->fetchAll('SELECT entity_type_id, COUNT(*) cnt FROM eav_attribute GROUP BY entity_type_id ORDER BY entity_type_id');
foreach ($rows as $row) {
    echo $row['entity_type_id'] . '|' . $row['cnt'] . PHP_EOL;
}

echo PHP_EOL . "== eav_entity_attribute count ==" . PHP_EOL;
$rows = $conn->fetchAll('SELECT attribute_set_id, COUNT(*) cnt FROM eav_entity_attribute GROUP BY attribute_set_id ORDER BY attribute_set_id');
foreach ($rows as $row) {
    echo $row['attribute_set_id'] . '|' . $row['cnt'] . PHP_EOL;
}
