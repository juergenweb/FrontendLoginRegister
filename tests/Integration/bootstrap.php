<?php

declare(strict_types=1);

/*
 * Bootstraps a real ProcessWire instance for the integration tests in this directory, so they
 * can exercise wire()-dependent code (database lookups, module config, real Page/User objects)
 * that the pure tests/Unit/ suite deliberately avoids.
 *
 * Where to find your ProcessWire installation is configured either:
 *  - via the PW_ROOT_PATH environment variable, or
 *  - by copying tests/Integration/pw-root.php.dist to tests/Integration/pw-root.php (gitignored)
 *    and editing the path in it.
 *
 * If neither is set, or the configured path doesn't actually contain a ProcessWire index.php,
 * FLR_INTEGRATION_PW_ROOT is left null and IntegrationTestCase skips every test in this suite
 * instead of failing - so an unconfigured checkout still runs "composer test" (the tests/Unit/
 * suite) cleanly.
 */

$pwRootPath = getenv('PW_ROOT_PATH');
if ($pwRootPath === false || $pwRootPath === '') {
    $pwRootPath = null;
}

if ($pwRootPath === null) {
    $localConfigFile = __DIR__ . '/pw-root.php';
    if (is_file($localConfigFile)) {
        $configured = require $localConfigFile;
        if (is_string($configured) && $configured !== '') {
            $pwRootPath = $configured;
        }
    }
}

if ($pwRootPath !== null) {
    $pwRootPath = rtrim(str_replace('\\', '/', $pwRootPath), '/');
}

if ($pwRootPath !== null && is_file($pwRootPath . '/index.php')) {
    define('FLR_INTEGRATION_PW_ROOT', $pwRootPath);
} else {
    define('FLR_INTEGRATION_PW_ROOT', null);
}

if (FLR_INTEGRATION_PW_ROOT !== null && !defined('PROCESSWIRE')) {
    // Bootstrapping ProcessWire from an external script - this is PW's own documented technique
    // (see "Bootstrapping ProcessWire" in the ProcessWire docs: including index.php from your own
    // script makes wire()/the ProcessWire API available afterward). Some ProcessWire versions
    // compute paths from $_SERVER during bootstrap, which isn't populated in a CLI/PHPUnit
    // context - fill in harmless defaults for that if they're missing, rather than requiring the
    // caller to already know about this.
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $_SERVER['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'] ?? (FLR_INTEGRATION_PW_ROOT . '/index.php');
    $_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';

    require FLR_INTEGRATION_PW_ROOT . '/index.php';
}

require_once __DIR__ . '/IntegrationTestCase.php';
