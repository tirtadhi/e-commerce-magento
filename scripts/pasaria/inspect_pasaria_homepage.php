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

$row = $conn->fetchRow(
    "SELECT page_id, identifier, title, is_active FROM cms_page WHERE identifier = 'pasaria-home' LIMIT 1"
);

if (!$row) {
    echo 'Pasaria homepage not found' . PHP_EOL;
    exit(1);
}

echo 'page_id=' . $row['page_id'] . PHP_EOL;
echo 'identifier=' . $row['identifier'] . PHP_EOL;
echo 'title=' . $row['title'] . PHP_EOL;
echo 'is_active=' . $row['is_active'] . PHP_EOL;
