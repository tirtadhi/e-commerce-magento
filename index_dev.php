<?php
// Bypass installation check for development
$_SERVER['MAGE_REQUIRE_IS_INSTALLED'] = null;

// Redirect atau load Magento
require __DIR__ . '/pub/index.php';
?>
