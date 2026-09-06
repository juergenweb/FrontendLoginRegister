<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/RegisterPageBusinessLogicHarness.php';

use ProcessWire\User;

/**
 * Integration coverage for FrontendLoginRegisterPages::getDateFormat() against the real
 * FrontendForms module configuration.
 */
final class GetDateFormatIntegrationTest extends IntegrationTestCase
{
    public function testReturnsTheDefaultLanguageDateFormatForTheDefaultLanguageUser(): void
    {
        // For the site's default-language visitor, getDateFormat() always resolves to the base
        // "input_dateformat" value - whether or not LanguageSupport is even installed (both
        // branches collapse to the same value in that case), so wire('user') (a guest, on the
        // default language unless something very unusual is going on) is a safe real-world input.
        $frontendformsConfig = $this->wire('modules')->getConfig('FrontendForms');
        $expected = $frontendformsConfig['input_dateformat'] ?? '';

        $harness = new RegisterPageBusinessLogicHarness();
        $result = $harness->callGetDateFormat($this->wire('user'));

        $this->assertSame($expected, $result);
    }

    public function testUsesThePerLanguageOverrideOrFallsBackForANonDefaultLanguageUser(): void
    {
        if (!$this->wire('modules')->isInstalled('LanguageSupport')) {
            $this->markTestSkipped('LanguageSupport is not installed on this site.');
        }

        $nonDefaultLanguage = null;
        foreach ($this->wire('languages') as $language) {
            if (!$language->isDefault()) {
                $nonDefaultLanguage = $language;
                break;
            }
        }
        if (!$nonDefaultLanguage) {
            $this->markTestSkipped('Only the default language is installed on this site.');
        }

        // a transient, unsaved User is enough here - getDateFormat() only ever reads $user->language
        $user = new User();
        $user->language = $nonDefaultLanguage;

        $frontendformsConfig = $this->wire('modules')->getConfig('FrontendForms');
        $dateProperty = 'input_dateformat__' . $nonDefaultLanguage->id;
        // mirrors getDateFormat()'s own array_key_exists() check exactly - no emptiness fallback,
        // unlike the multi-language mail-text lookup used elsewhere in the module
        $expected = array_key_exists($dateProperty, $frontendformsConfig)
            ? $frontendformsConfig[$dateProperty]
            : $frontendformsConfig['input_dateformat'];

        $harness = new RegisterPageBusinessLogicHarness();
        $result = $harness->callGetDateFormat($user);

        $this->assertSame($expected, $result);
    }
}
