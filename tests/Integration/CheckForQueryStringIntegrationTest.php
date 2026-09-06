<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/CreatesVerificationLinksFormHarness.php';

/**
 * Integration coverage for CreatesVerificationLinks::checkForQueryString() against a live
 * ProcessWire instance.
 *
 * Only the "querystring missing, redirects disabled" case is covered. Two other cases were tried
 * and dropped again, both because they turned out to depend on things this sandbox cannot verify
 * against a real ProcessWire instance, and both failed in a way that cost a full test run to
 * diagnose:
 *  - The actual redirect (querystring missing, default redirect=true) cannot be exercised at all:
 *    the real $session->redirect() call sends headers and calls exit(), which kills the whole
 *    PHPUnit run. A Session::redirect() "before" hook that threw an exception instead of letting
 *    the real method run did not prevent it either - ProcessWire appears to catch exceptions
 *    raised inside hook callbacks internally rather than letting them propagate - so the real
 *    redirect()/exit() ran anyway.
 *  - The "querystring present" case (calling checkForQueryString() after setting
 *    $_GET/$_SERVER['QUERY_STRING'] from PHP) turned out not to work: ProcessWire's
 *    $input->queryStringClean() did not pick up those values (confirmed by the resulting
 *    $this->queryString coming back empty), most likely because WireInput reads/caches the real
 *    request's query string once at bootstrap rather than re-reading it live.
 *
 * checkForUser()/checkQueryStringUser()'s own logic is still covered directly, by
 * CheckForUserIntegrationTest and CreatesVerificationLinksIntegrationTest, which both set
 * $this->queryString directly via the harness rather than simulating a request.
 */
final class CheckForQueryStringIntegrationTest extends IntegrationTestCase
{
    public function testReturnsEmptyStringAndDoesNotRedirectWhenTheQueryStringIsMissingAndRedirectIsFalse(): void
    {
        // deliberately no querystring present
        $harness = new CreatesVerificationLinksFormHarness();
        $result = $harness->callCheckForQueryString('activationcode', false);

        // if this had triggered a redirect, the process would have exited before reaching here
        $this->assertSame('', $result);
    }
}
