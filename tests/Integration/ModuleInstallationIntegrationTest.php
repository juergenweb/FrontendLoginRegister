<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';

/**
 * Basic smoke test that the module is actually installed and configured correctly on the
 * ProcessWire instance the integration suite runs against - a cheap early warning if the test
 * setup itself is broken (wrong site, module not installed, required pages missing), before
 * chasing failures in the more specific integration tests.
 */
final class ModuleInstallationIntegrationTest extends IntegrationTestCase
{
    public function testModuleIsInstalledAndLoadable(): void
    {
        $this->assertTrue(
            $this->wire('modules')->isInstalled('FrontendLoginRegister'),
            'The FrontendLoginRegister module is not installed on this ProcessWire instance.'
        );

        $module = $this->wire('modules')->get('FrontendLoginRegister');

        $this->assertNotNull($module);
        $this->assertInstanceOf(\ProcessWire\Module::class, $module);
    }

    public function testModuleConfigurationHasTheExpectedKeys(): void
    {
        $config = $this->wire('modules')->getConfig('FrontendLoginRegister');

        $this->assertIsArray($config);

        // A handful of the module's core settings - not exhaustive, just enough to catch a
        // configuration array that is empty or wildly different from what the module expects.
        $expectedKeys = [
            'input_registration',
            'input_profile',
            'input_privacy',
            'input_delete',
            'input_remind',
            'input_selectlogin',
            'input_roles',
            'input_max_filesize',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $config, "Expected config key \"$key\" is missing.");
        }
    }

    public function testRequiredVerificationPagesExist(): void
    {
        // every template the module's own createCodeLink()/redirect logic targets by name -
        // if any of these pages is missing, the corresponding feature is broken site-wide
        $requiredTemplates = [
            'fl_loginpage',
            'fl_activationpage',
            'fl_deleteaccountpage',
            'fl_deleterequestpage',
            'fl_recoverylogindatapage',
            'fl_unlockaccountpage',
            'fl_registerpage',
            'fl_forgotlogindatapage',
        ];

        foreach ($requiredTemplates as $templateName) {
            $page = $this->wire('pages')->get("template=$templateName,include=all");
            $this->assertNotEquals(
                0,
                $page->id,
                "No page found using template \"$templateName\" - the module's required pages "
                . 'do not seem to be fully installed on this site.'
            );
        }
    }
}
