<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';

use ProcessWire\User;

/**
 * Integration coverage for the selectorValue() sanitizing fix applied to
 * RegisterPage::getUserByActivationCode() (previously $this->wire('users')->get('email=' .
 * $email) - unsanitized user input straight into a ProcessWire selector string).
 *
 * This can only really be checked against a real ProcessWire selector engine - a pure unit test
 * has nothing to run the selector string against. It exercises the exact
 * $this->wire('sanitizer')->selectorValue($email) . $this->wire('users')->get('email=' . ...)
 * pattern the fixed code uses, without needing to construct the full RegisterPage (which drags
 * in FrontendForms field building unrelated to this specific fix).
 */
final class SelectorSanitizingIntegrationTest extends IntegrationTestCase
{
    /** @var User[] */
    private array $createdUsers = [];

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

    public function testSanitizedLookupStillFindsAGenuineEmailAddress(): void
    {
        $email = 'flr-integration-test-' . bin2hex(random_bytes(4)) . '@example.com';
        $expected = $this->createTestUser($email);

        $found = $this->wire('users')->get(
            'email=' . $this->wire('sanitizer')->selectorValue($email)
        );

        $this->assertSame($expected->id, $found->id);
    }

    public function testSanitizedLookupDoesNotMatchOrErrorOnASelectorMetacharacterLadenValue(): void
    {
        // a genuine account that must NOT be matched by the crafted value below
        $unrelated = $this->createTestUser('flr-unrelated-' . bin2hex(random_bytes(4)) . '@example.com');

        // not a valid email address, but exactly the kind of string a selector-value sanitizer is
        // there to neutralize: a comma (selector field separator), a pipe (OR-value separator)
        // and an equals sign, all in one string
        $malicious = 'nobody@example.com,roles=' . $unrelated->roles->first()?->id . '|title=*';

        $found = $this->wire('users')->get(
            'email=' . $this->wire('sanitizer')->selectorValue($malicious)
        );

        // no user has that literal string as their email - the lookup must come back empty
        // (id 0, ProcessWire's NullPage-like "not found" User) rather than throwing, or - if the
        // sanitizing were missing/broken - accidentally matching $unrelated via the injected
        // roles= condition
        $this->assertSame(0, $found->id);
    }

    private function createTestUser(string $email): User
    {
        $user = new User();
        $user->name = 'flr-integration-test-' . bin2hex(random_bytes(4));
        $user->email = $email;
        $user->pass = bin2hex(random_bytes(12));
        $guestRole = $this->wire('roles')->get('guest');
        if ($guestRole->id) {
            $user->addRole($guestRole);
        }
        $user->save();

        $this->createdUsers[] = $user;

        return $user;
    }
}
