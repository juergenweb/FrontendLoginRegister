<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/RegisterPageBusinessLogicHarness.php';

use FrontendForms\Link;
use FrontendLoginRegister\RegisterPage;

/**
 * Integration coverage for two independent bits of FrontendLoginRegisterPages/RegisterPage logic:
 *  - FrontendLoginRegisterPages::loginLink(), which builds a Link pointing at the real login page
 *  - RegisterPage::getDeleteDateTime(), which calculates the date/time a not-yet-activated account
 *    is scheduled to be auto-deleted, from the module's configured "input_delete" days setting
 *
 * Both are exercised on/via a guest-constructed RegisterPage, which is safe (RegisterPage only
 * redirects a guest-constructed instance away if a user IS logged in).
 */
final class LoginLinkAndDeleteDateTimeIntegrationTest extends IntegrationTestCase
{
    public function testLoginLinkPointsAtTheRealLoginPage(): void
    {
        $page = new RegisterPage();
        $link = $page->loginLink();

        $this->assertInstanceOf(Link::class, $link);

        $loginPage = $this->wire('pages')->get('template=fl_loginpage');
        $this->assertSame($loginPage->url, $link->getAttribute('href'));
    }

    public function testGetDeleteDateTimeAddsTheConfiguredNumberOfDaysToToday(): void
    {
        $harness = new RegisterPageBusinessLogicHarness();
        $result = $harness->callGetDeleteDateTime();

        // exact same computation as RegisterPage::getDeleteDateTime(), using the real module
        // configuration - not a hardcoded number of days
        $inputDelete = (int)($this->wire('modules')->getConfig('FrontendLoginRegister')['input_delete'] ?? 0);
        $expectedDate = date('Y-m-d', strtotime(date('Y-m-d') . ' + ' . $inputDelete . ' days'));

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $result);
        $this->assertStringStartsWith($expectedDate . ' ', $result);
    }

    public function testGetDeleteDateTimeReturnsAUnixTimestampWhenRequested(): void
    {
        $harness = new RegisterPageBusinessLogicHarness();
        $result = $harness->callGetDeleteDateTime(true);

        // this is the branch that used to be declared "string" while actually returning an int -
        // asserting the type here is the point of this test, on top of the value check below
        $this->assertIsInt($result);

        $inputDelete = (int)($this->wire('modules')->getConfig('FrontendLoginRegister')['input_delete'] ?? 0);
        $expectedDate = date('Y-m-d', strtotime(date('Y-m-d') . ' + ' . $inputDelete . ' days'));
        $expectedTimestamp = strtotime($expectedDate . ' ' . date('H:i'));

        // small delta to tolerate a minute boundary between this computation and the one inside
        // getDeleteDateTime() itself
        $this->assertEqualsWithDelta($expectedTimestamp, $result, 60);
    }
}
