<?php
// Test file untuk verify PHP dan Magento
$info = [
    'PHP Version' => PHP_VERSION,
    'Server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'Request URI' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
];

echo "=== Magento 2 Test Page ===\n\n";
echo "✓ PHP is working!\n\n";

foreach ($info as $key => $value) {
    echo "$key: $value\n";
}

// Check if Magento bootstrap exists
if (file_exists(__DIR__ . '/../app/bootstrap.php')) {
    echo "\n✓ Magento files found\n";
} else {
    echo "\n❌ Magento files NOT found\n";
}

// Try to load Magento
try {
    require __DIR__ . '/../app/bootstrap.php';
    echo "✓ Magento bootstrap loaded successfully\n";
} catch (Exception $e) {
    echo "❌ Error loading Magento: " . $e->getMessage() . "\n";
}

echo "\n📍 Your installation is at: " . dirname(__DIR__) . "\n";
echo "🔗 Try accessing: http://localhost/magento/pub/\n";
?>
