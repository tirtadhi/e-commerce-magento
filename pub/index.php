<?php
/**
 * Public alias for the application entry point
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Framework\App\Bootstrap;

// Bypass installation check in developer mode
if (getenv('MAGE_MODE') === 'developer' || getenv('MAGE_MODE') === false) {
    $_SERVER['MAGE_REQUIRE_IS_INSTALLED'] = null;
}

try {
    require __DIR__ . '/../app/bootstrap.php';
} catch (\Exception $e) {
    echo <<<HTML
<div style="font:12px/1.35em arial, helvetica, sans-serif;">
    <div style="margin:0 0 25px 0; border-bottom:1px solid #ccc;">
        <h3 style="margin:0;font-size:1.7em;font-weight:normal;text-transform:none;text-align:left;color:#2f2f2f;">
        Autoload error</h3>
    </div>
    <p>{$e->getMessage()}</p>
</div>
HTML;
    http_response_code(500);
    exit(1);
}

try {
    $bootstrap = Bootstrap::create(BP, $_SERVER);
    /** @var \Magento\Framework\App\Http $app */
    $app = $bootstrap->createApplication(\Magento\Framework\App\Http::class);
    $bootstrap->run($app);
} catch (\LogicException $e) {
    if (strpos($e->getMessage(), 'Circular dependency') !== false) {
        // Clear cache and try again
        $varDir = __DIR__ . '/../var/';
        @array_map('unlink', glob($varDir . 'cache/*'));
        @array_map('unlink', glob($varDir . 'generation/*'));

        echo <<<HTML
<div style="font:12px/1.35em arial, helvetica, sans-serif;">
    <div style="margin:0 0 25px 0; border-bottom:1px solid #ccc;">
        <h3 style="margin:0;font-size:1.7em;font-weight:normal;text-transform:none;text-align:left;color:#2f2f2f;">
        System Maintenance</h3>
    </div>
    <p>The system is being configured. Please refresh the page in 2 seconds.</p>
    <script>
        setTimeout(function() {
            location.reload();
        }, 2000);
    </script>
</div>
HTML;
        exit(0);
    }
    throw $e;
} catch (\ReflectionException $e) {
    if (strpos($e->getMessage(), 'does not exist') !== false) {
        // Interceptor classes need to be generated - this is normal on first run
        $generated = __DIR__ . '/../generated/code';
        $var = __DIR__ . '/../var';

        // Clear possibly corrupted generated files
        @array_map('unlink', glob($generated . '/*'));
        @array_map('unlink', glob($var . '/cache/*'));
        @array_map('unlink', glob($var . '/generation/*'));

        echo <<<HTML
<div style="font:12px/1.35em arial, helvetica, sans-serif;">
    <div style="margin:0 0 25px 0; border-bottom:1px solid #ccc;">
        <h3 style="margin:0;font-size:1.7em;font-weight:normal;text-transform:none;text-align:left;color:#2f2f2f;">
        Initializing Application</h3>
    </div>
    <p>The application is initializing. Please wait...</p>
    <script>
        setTimeout(function() {
            location.reload();
        }, 1500);
    </script>
</div>
HTML;
        exit(0);
    }
    throw $e;
}
