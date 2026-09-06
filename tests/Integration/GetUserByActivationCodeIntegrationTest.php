<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/RegisterPageBusinessLogicHarness.php';

use ProcessWire\User;

/**
 * Integration coverage for RegisterPage::getUserByActivationCode()'s actual business rule: a user
 * is only returned when BOTH the email matches AND the account still carries an activation code
 * (fl_activation) - i.e. it is a genuinely pending registration, not an already-activated account
 * or a guessed email address. SelectorSanitizingIntegrationTest already covers the selectorValue()
 * sanitizing this method relies on in isolation; this test exercises the method itself.
 */
final class GetUserByActivationCodeIntegrationTest extends IntegrationTestCase
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

    public function testReturnsTheUserWhenTheEmailMatchesAndAnActivationCodeIsStillPending(): void
    {
        $email = 'flr-integration-test-' . bin2hex(random_bytes(4)) . '@example.com';
        $user = $this->createTestUser($email, 'flr-test-' . bin2hex(random_bytes(8)));

        $harness = new RegisterPageBusinessLogicHarness();
        $found = $harness->callGetUserByActivationCode($email);

        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->id);
    }

    public function testReturnsNullWhenTheAccountHasAlreadyBeenActivated(): void
    {
        // same email, but no (or an empty) activation code - i.e. an already-activated account
        $email = 'flr-integration-test-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->createTestUser($email, '');

        $harness = new RegisterPageBusinessLogicHarness();
        $found = $harness->callGetUserByActivationCode($email);

        $this->assertNull($found);
    }

    public function testReturnsNullWhenNoAccountHasThatEmailAtAll(): void
    {
        $harness = new RegisterPageBusinessLogicHarness();
        $found = $harness->callGetUserByActivationCode(
            'flr-integration-test-nobody-' . bin2hex(random_bytes(4)) . '@example.com'
        );

        $this->assertNull($found);
    }

    private function createTestUser(string $email, string $activationCode): User
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

        $user->of(false);
        $user->fl_activation = $activationCode;
        $user->save();
        $user->of(true);

        $this->createdUsers[] = $user;

        return $user;
    }
}
