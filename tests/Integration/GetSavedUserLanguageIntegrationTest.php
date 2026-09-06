<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';

use FrontendLoginRegister\FrontendLoginRegisterPages;
use ProcessWire\User;
use ReflectionClass;
use ReflectionMethod;

/**
 * Integration coverage for FrontendLoginRegisterPages::getSavedUserLanguage(), which reads the
 * user's language straight from the database (via the language field's loadPageField()) instead
 * of trusting $user->language, so it needs a real multi-language ProcessWire instance to exercise
 * meaningfully.
 *
 * FrontendLoginRegisterPages extends the FrontendForms\Form class, whose constructor needs a lot
 * of unrelated setup (a form ID, the FrontendForms module, ...) that has nothing to do with this
 * one method, and getSavedUserLanguage() itself only touches $this->wire(), never any property set
 * up by that constructor. So instead of constructing a real instance, this test uses reflection to
 * create a bare, uninitialized instance and invoke the (protected) method directly.
 */
final class GetSavedUserLanguageIntegrationTest extends IntegrationTestCase
{
    /** @var User[] */
    private array $createdUsers = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->wire('modules')->isInstalled('LanguageSupport')) {
            $this->markTestSkipped('LanguageSupport is not installed on this site.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUsers as $user) {
            if ($user->id) {
                $this->wire('users')->delete($user);
            }
        }
        $this->createdUsers = [];

        parent::tearDown();
    }

    public function testReturnsTheLanguageStoredInTheDatabaseForAnExistingUser(): void
    {
        $language = $this->wire('user')->language;

        $user = new User();
        $user->name = 'flr-integration-test-' . bin2hex(random_bytes(4));
        $user->email = $user->name . '@example.com';
        $user->pass = bin2hex(random_bytes(12));
        $guestRole = $this->wire('roles')->get('guest');
        if ($guestRole->id) {
            $user->addRole($guestRole);
        }
        $user->save();
        $user->of(false);
        $user->language = $language;
        $user->save();
        $user->of(true);
        $this->createdUsers[] = $user;

        $this->assertNotEquals(0, $user->id, 'Test user was not actually saved.');

        $result = $this->invokeGetSavedUserLanguage($user);

        $this->assertSame($language->id, $result->id);
    }

    public function testFallsBackToTheUserObjectsOwnLanguageWhenTheUserIsNotYetSaved(): void
    {
        // a User with id === 0 (never saved) takes the "else" branch, which just returns
        // $user->language directly instead of looking it up via loadPageField()
        $user = new User();
        $user->language = $this->wire('user')->language;

        $this->assertSame(0, $user->id);

        $result = $this->invokeGetSavedUserLanguage($user);

        $this->assertSame($user->language->id, $result->id);
    }

    private function invokeGetSavedUserLanguage(User $user): \ProcessWire\Language
    {
        $reflectionClass = new ReflectionClass(FrontendLoginRegisterPages::class);
        $instance = $reflectionClass->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(FrontendLoginRegisterPages::class, 'getSavedUserLanguage');
        $method->setAccessible(true);

        return $method->invoke($instance, $user);
    }
}
