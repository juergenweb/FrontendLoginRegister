<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/CreatesVerificationLinksFormHarness.php';

use ProcessWire\User;

/**
 * Integration coverage for CreatesVerificationLinks::checkForUser(), the Form-aware wrapper around
 * checkQueryStringUser() (already covered directly by CreatesVerificationLinksIntegrationTest) that
 * additionally hides the form and sets a danger alert when no user is found.
 *
 * Sets $this->queryString directly via the harness's setQueryStringValue() rather than going
 * through checkForQueryString() with a simulated $_GET/$_SERVER request: setting those from PHP
 * after ProcessWire has already bootstrapped turned out not to be picked up by
 * $input->queryStringClean() in practice (confirmed by an earlier, now-corrected false-positive
 * here - an empty $this->queryString made the "no user matches" case match an unrelated real user
 * on the site whose fl_activation field happened to be empty too).
 */
final class CheckForUserIntegrationTest extends IntegrationTestCase
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

    public function testReturnsTrueAndLeavesTheFormVisibleWhenTheCodeMatchesAUser(): void
    {
        $code = 'flr-test-' . bin2hex(random_bytes(8));
        $this->createTestUser($code);

        $harness = new CreatesVerificationLinksFormHarness();
        $harness->setQueryStringValue($code);

        $result = $harness->callCheckForUser('activation');

        $this->assertTrue($result);
        $this->assertTrue($harness->getShowForm());
    }

    public function testReturnsFalseAndHidesTheFormWhenNoUserMatchesTheCode(): void
    {
        $harness = new CreatesVerificationLinksFormHarness();
        // a code that is guaranteed not to be empty and not to match any real user
        $harness->setQueryStringValue('flr-test-nobody-' . bin2hex(random_bytes(8)));

        $result = $harness->callCheckForUser('activation');

        $this->assertFalse($result);
        $this->assertFalse($harness->getShowForm());
    }

    private function createTestUser(string $activationCode): User
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
