<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

use FrontendLoginRegister\LoginPage;
use ProcessWire\User;

require_once __DIR__ . '/bootstrap.php';

/**
 * Real, guest-constructed LoginPage instance for exercising LoginPage::checkIfAccountLocked(),
 * which is protected.
 *
 * LoginPage::__construct() redirects away (and exits the process!) if a user IS logged in, so this
 * harness must only ever be constructed as a guest - fine here, since checkIfAccountLocked() does
 * not depend on being logged in (it just inspects the given $user's fl_unlockaccount field).
 */
class LoginPageBusinessLogicHarness extends LoginPage
{
    public function callCheckIfAccountLocked(User $user): bool
    {
        return $this->checkIfAccountLocked($user);
    }
}
