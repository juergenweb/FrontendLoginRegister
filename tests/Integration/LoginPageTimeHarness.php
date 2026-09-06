<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

use FrontendLoginRegister\LoginPage;

require_once __DIR__ . '/bootstrap.php';

/**
 * Real, guest-constructed LoginPage instance for exercising LoginPage::secondsToTime(), which is
 * protected. secondsToReadable() is already public and does not need a pass-through here - tests
 * can call it directly on any LoginPage instance.
 *
 * LoginPage::__construct() redirects away (and exits the process!) if a user IS logged in, so this
 * harness must only ever be constructed as a guest - fine here, since secondsToTime() does not
 * depend on being logged in.
 */
class LoginPageTimeHarness extends LoginPage
{
    public function callSecondsToTime(int $seconds): string
    {
        return $this->secondsToTime($seconds);
    }
}
