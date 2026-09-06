<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/CreatesVerificationLinksHarness.php';

use ProcessWire\User;

/**
 * Integration coverage for CreatesVerificationLinks::checkQueryStringUser() against a real
 * ProcessWire database: does it actually find the user a given code belongs to, and does it
 * correctly leave $this->user alone when nothing matches?
 *
 * This directly protects the "$this->user vs. the correct target user" mix-up that turned out to
 * be a recurring bug pattern across several page classes earlier in this project - unlike those
 * bugs, this method's whole job is REASSIGNING $this->user, so a regression here would be exactly
 * the kind of thing a pure unit test (no real users(), no real selector) can't catch.
 */
final class CreatesVerificationLinksIntegrationTest extends IntegrationTestCase
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

    public function testCheckQueryStringUserSwapsUserToTheMatchingAccount(): void
    {
        $code = 'flr-test-' . bin2hex(random_bytes(8));
        $testUser = $this->createTestUserWithActivationCode($code);

        $harness = new CreatesVerificationLinksHarness();
        $harness->queryString = $code;
        // starts out as the current visitor (a guest, in a real request) - checkQueryStringUser()
        // should overwrite this with the account the code belongs to
        $harness->user = $this->wire('user');

        $found = $harness->callCheckQueryStringUser('activationcode');

        $this->assertTrue($found);
        $this->assertSame($testUser->id, $harness->user->id);
    }

    public function testCheckQueryStringUserReturnsFalseAndLeavesUserUntouchedWhenCodeDoesNotMatch(): void
    {
        $harness = new CreatesVerificationLinksHarness();
        $harness->queryString = 'no-such-code-' . bin2hex(random_bytes(8));
        $guest = $this->wire('user');
        $harness->user = $guest;

        $found = $harness->callCheckQueryStringUser('activationcode');

        $this->assertFalse($found);
        // still the exact same (guest) object - untouched, not swapped to anything
        $this->assertSame($guest, $harness->user);
    }

    private function createTestUserWithActivationCode(string $activationCode): User
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
        $user->fl_activation = $activationCode;
        $user->save();
        $user->of(true);

        $this->createdUsers[] = $user;

        return $user;
    }
}
