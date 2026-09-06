<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

use FrontendLoginRegister\RegisterPage;
use ProcessWire\User;

require_once __DIR__ . '/bootstrap.php';

/**
 * Real, fully-constructed RegisterPage instance for exercising independent bits of business logic
 * against a live ProcessWire instance:
 *  - RegisterPage::getUserByActivationCode(), which looks up a not-yet-activated account by email
 *  - FrontendLoginRegisterPages::getDateFormat(), inherited from the shared base class, which
 *    resolves the configured date format for a given user's language
 *  - RegisterPage::getDeleteDateTime(), which calculates the date/time a not-yet-activated
 *    account is scheduled to be auto-deleted
 *  - FrontendLoginRegisterPages::getRedirectSuccess(), the protected getter for the URL set via
 *    the public setRedirectSuccess()
 *
 * RegisterPage::__construct() redirects away (and exits the process!) if a user IS logged in, so
 * this harness must only ever be constructed as a guest - fine here, since none of these methods
 * depend on being logged in.
 */
class RegisterPageBusinessLogicHarness extends RegisterPage
{
    public function callGetUserByActivationCode(string $email): ?User
    {
        return $this->getUserByActivationCode($email);
    }

    public function callGetDateFormat(User $user): string
    {
        return $this->getDateFormat($user);
    }

    public function callGetDeleteDateTime(bool $timestamp = false): string|int
    {
        return $this->getDeleteDateTime($timestamp);
    }

    public function callGetRedirectSuccess(): string
    {
        return $this->getRedirectSuccess();
    }
}
