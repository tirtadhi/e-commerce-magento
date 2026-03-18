<?php
// This file helps access Magento setup

// Redirect to pub folder
$webroot = dirname(__DIR__);

// Check if we're already in pub
if (basename(__DIR__) !== 'pub') {
    $location = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
    if (strpos($location, '/pub') === false) {
        // Redirect to pub/index.php
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = $protocol . $_SERVER['HTTP_HOST'] . str_replace('\\', '/', dirname($_SERVER['REQUEST_URI'])) . '/pub/';
        header('Location: ' . $url);
        exit;
    }
}

// Load Magento application
require __DIR__ . '/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$app = $bootstrap->createApplication('Magento\Framework\App\Http');
$bootstrap->run($app);
?>
