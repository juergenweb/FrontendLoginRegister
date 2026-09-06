<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/CreatesVerificationLinksHarness.php';

use ProcessWire\User;

/**
 * Integration coverage for CreatesVerificationLinks::createActivationLink(),
 * createNotRegisteredLink(), createCodeLink() and daysToDelete() against a real ProcessWire
 * instance - these all depend on the module's own system pages (fl_activationpage, ...) actually
 * existing, and daysToDelete() depends on real module configuration, neither of which a pure
 * unit test can exercise.
 */
final class VerificationLinksIntegrationTest extends IntegrationTestCase
{
    /** @var User[] */
    private array $createdUsers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $activationPage = $this->wire('pages')->get('template=fl_activationpage,include=all');
        if (!$activationPage->id) {
            $this->markTestSkipped(
                'No page with template "fl_activationpage" found - the module\'s required pages '
                . 'do not seem to be installed on this site.'
            );
        }
    }

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

    public function testCreateActivationLinkPointsAtTheActivationPageAndCarriesTheActivationCode(): void
    {
        $code = 'flr-test-' . bin2hex(random_bytes(8));
        $user = $this->createTestUser($code);

        $harness = new CreatesVerificationLinksHarness();
        $html = $harness->callCreateActivationLink($user);

        $activationPage = $this->wire('pages')->get('template=fl_activationpage,include=all');

        $this->assertStringContainsString($activationPage->httpUrl, $html);
        $this->assertStringContainsString('activationcode=' . $code, $html);
    }

    public function testCreateNotRegisteredLinkUsesTheNotRegisteredQuerystringNameInstead(): void
    {
        $code = 'flr-test-' . bin2hex(random_bytes(8));
        $user = $this->createTestUser($code);

        $harness = new CreatesVerificationLinksHarness();
        $html = $harness->callCreateNotRegisteredLink($user);

        // same target page and code as the activation link, but a different querystring name -
        // this is what tells ActivationPage::__construct() which of the two branches (verify vs.
        // delete-the-account) to take
        $this->assertStringContainsString('notregisteredcode=' . $code, $html);
        $this->assertStringNotContainsString('activationcode=' . $code, $html);
    }

    public function testCreateCodeLinkDerivesTheQuerystringNameFromTheTemplateNameWhenNoneIsGiven(): void
    {
        $harness = new CreatesVerificationLinksHarness();
        $code = 'flr-test-' . bin2hex(random_bytes(8));

        // "fl_deleteaccountpage" -> "deleteaccountcode", mirroring DeleteAccountPage's own
        // checkForQueryString('deleteaccountcode') call
        $html = $harness->callCreateCodeLink('fl_deleteaccountpage', $code);

        $this->assertStringContainsString('deleteaccountcode=' . $code, $html);
    }

    public function testDaysToDeleteReadsTheRealInputDeleteConfigValue(): void
    {
        $config = $this->wire('modules')->getConfig('FrontendLoginRegister');
        $inputDelete = (int)($config['input_delete'] ?? 0);

        $registeredDaysAgo = 5;
        $user = $this->createTestUser('flr-test-' . bin2hex(random_bytes(8)));
        $user->of(false);
        $user->created = time() - ($registeredDaysAgo * 86400);
        $user->save();
        $user->of(true);

        $harness = new CreatesVerificationLinksHarness();
        $harness->loginregisterConfig = $config;

        $this->assertSame($registeredDaysAgo + $inputDelete, $harness->callDaysToDelete($user));
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
