<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';

use FrontendLoginRegister\LoginPage;
use FrontendLoginRegister\RegisterPage;

/**
 * Integration coverage for FrontendLoginRegister::__call(), the magic factory method that lets
 * callers do $modules->get('FrontendLoginRegister')->LoginPage() instead of "new LoginPage()"
 * directly.
 *
 * Only LoginPage and RegisterPage are exercised here - both are already used elsewhere in this
 * suite as guest-safe construction targets (neither redirects unless a user is logged in). Several
 * of the module's other page classes (ActivationPage, DeleteAccountPage, RecoveryLogindataPage,
 * UnlockAccountPage, ProfilePage, ...) either read the querystring or redirect guests away in
 * their own constructors, so calling them here would risk the same silent exit()-kills-the-suite
 * failure this project's other integration tests ran into while under development.
 */
final class ModuleMagicCallIntegrationTest extends IntegrationTestCase
{
    public function testLoginPageFactoryMethodReturnsARealLoginPageInstance(): void
    {
        $module = $this->wire('modules')->get('FrontendLoginRegister');
        $page = $module->LoginPage();

        $this->assertInstanceOf(LoginPage::class, $page);
    }

    public function testRegisterPageFactoryMethodReturnsARealRegisterPageInstance(): void
    {
        $module = $this->wire('modules')->get('FrontendLoginRegister');
        $page = $module->RegisterPage();

        $this->assertInstanceOf(RegisterPage::class, $page);
    }

    public function testReturnsNullForAnUnknownPageName(): void
    {
        $module = $this->wire('modules')->get('FrontendLoginRegister');

        $this->assertNull($module->ThisPageClassDoesNotExist());
    }
}
