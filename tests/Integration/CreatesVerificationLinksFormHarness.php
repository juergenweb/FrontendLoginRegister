<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

use FrontendLoginRegister\RegisterPage;

require_once __DIR__ . '/bootstrap.php';

/**
 * Real, fully-constructed RegisterPage instance for exercising the two CreatesVerificationLinks
 * methods that need the full Form context - unlike the rest of the trait's methods, which the
 * bare WireData-based CreatesVerificationLinksHarness already covers:
 *  - checkForQueryString() reads $this->segments and redirects via the real session
 *  - checkForUser() calls the Form base class's setAlert()/getAlert() and sets $this->showForm
 *
 * RegisterPage::__construct() redirects away (and exits the process!) if a user IS logged in, so
 * this harness must only ever be constructed as a guest - fine here, since neither method depends
 * on being logged in.
 */
class CreatesVerificationLinksFormHarness extends RegisterPage
{
    public function callCheckForQueryString(string $queryStringName, string|bool $redirect = true): string
    {
        return $this->checkForQueryString($queryStringName, $redirect);
    }

    public function callCheckForUser(string $codeName): bool
    {
        return $this->checkForUser($codeName);
    }

    public function getShowForm(): bool
    {
        return $this->showForm;
    }

    /**
     * Set $this->queryString directly, bypassing checkForQueryString() - which reads the real
     * request's query string via $this->wire('input')->queryStringClean() and does not appear to
     * pick up query parameters set from PHP after ProcessWire has already bootstrapped. Tests that
     * only care about checkForUser()/checkQueryStringUser()'s own logic (not checkForQueryString()
     * itself) should set the value they need here instead.
     */
    public function setQueryStringValue(string $value): void
    {
        $this->queryString = $value;
    }
}
