<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

use PHPUnit\Framework\TestCase;

use function ProcessWire\wire;

/**
 * Base class for the integration test suite. Skips every test (instead of failing) when no real
 * ProcessWire installation was configured for it to bootstrap against - see bootstrap.php.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('FLR_INTEGRATION_PW_ROOT') || FLR_INTEGRATION_PW_ROOT === null) {
            $this->markTestSkipped(
                'No ProcessWire installation configured for the integration test suite - see '
                . 'tests/Integration/pw-root.php.dist for how to set one up. Skipping.'
            );
        }
    }

    /**
     * Thin wrapper around the global wire() function ProcessWire exposes once bootstrapped, so
     * test classes can write $this->wire('users') like any other ProcessWire-aware class.
     * @param string $name
     * @return mixed
     */
    protected function wire(string $name)
    {
        return wire($name);
    }
}
