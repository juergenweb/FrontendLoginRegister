<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/RegisterPageBusinessLogicHarness.php';

/**
 * Integration coverage for FrontendLoginRegisterPages::setRedirectSuccess()/getRedirectSuccess(),
 * the setter/getter pair that stores the URL to redirect to after a successful form submission.
 * setRedirectSuccess() is public and fluent; getRedirectSuccess() is protected, so it is exercised
 * through the harness.
 */
final class RedirectSuccessIntegrationTest extends IntegrationTestCase
{
    public function testDefaultsToAnEmptyString(): void
    {
        $harness = new RegisterPageBusinessLogicHarness();

        $this->assertSame('', $harness->callGetRedirectSuccess());
    }

    public function testStoresAndTrimsTheGivenUrl(): void
    {
        $harness = new RegisterPageBusinessLogicHarness();

        $result = $harness->setRedirectSuccess('  /some/target/url/  ');

        // fluent - returns the instance itself
        $this->assertSame($harness, $result);
        $this->assertSame('/some/target/url/', $harness->callGetRedirectSuccess());
    }
}
