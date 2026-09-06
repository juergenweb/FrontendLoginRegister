<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/LoginPageBusinessLogicHarness.php';

use ProcessWire\User;

/**
 * Integration coverage for LoginPage::checkIfAccountLocked(), which reports whether a user's
 * account is currently locked out (an unlock code is pending in the fl_unlockaccount field).
 */
final class CheckIfAccountLockedIntegrationTest extends IntegrationTestCase
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

    public function testReturnsTrueWhenTheUnlockAccountCodeIsSet(): void
    {
        $user = $this->createTestUser('flr-test-' . bin2hex(random_bytes(8)));

        $harness = new LoginPageBusinessLogicHarness();
        $result = $harness->callCheckIfAccountLocked($user);

        $this->assertTrue($result);
    }

    public function testReturnsFalseWhenTheUnlockAccountCodeIsEmpty(): void
    {
        $user = $this->createTestUser('');

        $harness = new LoginPageBusinessLogicHarness();
        $result = $harness->callCheckIfAccountLocked($user);

        $this->assertFalse($result);
    }

    private function createTestUser(string $unlockAccountCode): User
    {
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
        $user->fl_unlockaccount = $unlockAccountCode;
        $user->save();
        $user->of(true);

        $this->createdUsers[] = $user;

        return $user;
    }
}
