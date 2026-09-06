<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/ProfileImagePreviewHarnesses.php';

use ProcessWire\Field;
use ProcessWire\User;
use ProcessWire\WireException;

/**
 * Integration coverage for HandlesProfileImage::createProfileImagePreview(). Only the preview
 * markup is covered here, not saveProfileImage() - that method reads the uploaded filename via
 * Form::getValue(), which is only ever populated by a real form submission (its internal
 * valueStore), so testing it would need a simulated full POST submission rather than the
 * lightweight setup used by the rest of this suite.
 */
final class ProfileImagePreviewIntegrationTest extends IntegrationTestCase
{
    private ?User $loggedInUser = null;

    /** @var User[] */
    private array $createdUsers = [];

    /** @var string[] */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        // always log back out first, even if a test failed midway, so a later test in the same
        // PHPUnit process doesn't inherit a logged-in session
        if ($this->loggedInUser !== null) {
            $this->wire('session')->logout();
            $this->loggedInUser = null;
        }

        foreach ($this->createdUsers as $user) {
            if ($user->id) {
                $this->wire('users')->delete($user);
            }
        }
        $this->createdUsers = [];

        foreach ($this->tmpFiles as $tmpFile) {
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
        }
        $this->tmpFiles = [];

        parent::tearDown();
    }

    public function testCreateProfileImagePreviewReturnsEmptyWrapperForGuests(): void
    {
        // RegisterPage::__construct() redirects away (and exits the process!) if a user IS
        // logged in - so this harness may only ever be built as a guest, which is exactly the
        // case this test wants: the "else" branch of createProfileImagePreview().
        $harness = new RegisterPageProfileImagePreviewHarness();
        $imageFields = $harness->getImageFieldNames();
        if (!$imageFields) {
            $this->markTestSkipped(
                'No image field is configured for the registration form (module setting '
                . '"input_registration"), so there is nothing to render a preview for.'
            );
        }

        $html = $harness->callCreateProfileImagePreview($imageFields[0]);

        $this->assertStringContainsString('-' . $imageFields[0] . '-preview', $html);
        // guests never get an <img> tag or a "remove image" checkbox - just the empty wrapper div
        $this->assertStringNotContainsString('<img', $html);
    }

    public function testCreateProfileImagePreviewRendersImageAndRemoveCheckboxForLoggedInUserWithImage(): void
    {
        $imageField = $this->findConfiguredImageField('input_profile');
        if (!$imageField) {
            $this->markTestSkipped(
                'No image field is configured for the profile form (module setting "input_profile"), '
                . 'so there is nothing to render a preview for.'
            );
        }

        $plainPassword = 'flr-test-' . bin2hex(random_bytes(8));
        $user = new User();
        $user->name = 'flr-integration-test-' . bin2hex(random_bytes(4));
        $user->email = $user->name . '@example.com';
        $user->pass = $plainPassword;
        $guestRole = $this->wire('roles')->get('guest');
        if ($guestRole->id) {
            $user->addRole($guestRole);
        }
        $user->save();
        $this->createdUsers[] = $user;

        // a minimal (1x1 pixel) but genuinely valid PNG - ProcessWire's image handling needs real,
        // decodable image data (for getimagesize()/thumbnail generation), not just any bytes
        $tmpImage = $this->wire('files')->tempDir('flrProfileImagePreviewTest')->get() . 'test.png';
        $pngData = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );
        file_put_contents($tmpImage, $pngData);
        $this->tmpFiles[] = $tmpImage;

        $fieldName = $imageField->name;
        $user->of(false);
        $user->$fieldName->add($tmpImage);
        $user->save($fieldName);

        // Check the count with output formatting still off (via getUnformatted()), not after
        // $user->of(true): when the image field is limited to a single file (maxFiles == 1),
        // formatted access returns a single Pageimage object instead of a Pageimages array, and
        // count() on a Pageimage throws a TypeError.
        $imageCount = count($user->getUnformatted($fieldName));
        $user->of(true);

        if ($imageCount === 0) {
            $this->markTestSkipped('The test image could not be added to the "' . $fieldName . '" field.');
        }

        try {
            $loggedIn = $this->wire('session')->login($user->name, $plainPassword);
        } catch (WireException $e) {
            $loggedIn = null;
        }
        if (!$loggedIn || !$loggedIn->id) {
            $this->markTestSkipped(
                'Could not log in the test user programmatically (session->login() did not '
                . 'return a logged-in user) - this environment may block or hook programmatic login.'
            );
        }
        $this->loggedInUser = $loggedIn;

        // ProfilePage::__construct() redirects away (and exits the process!) if the user is a
        // guest - safe here since we are logged in at this point.
        $harness = new ProfilePageProfileImagePreviewHarness();
        $html = $harness->callCreateProfileImagePreview($fieldName);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('-' . $fieldName . '-image', $html);
        $this->assertStringContainsString('Remove this image', $html);
    }

    private function findConfiguredImageField(string $configKey): ?Field
    {
        $config = $this->wire('modules')->getConfig('FrontendLoginRegister');
        $fieldIds = $config[$configKey] ?? [];

        foreach ($fieldIds as $fieldId) {
            $field = $this->wire('fields')->get($fieldId);
            if (!$field || !$field->id) {
                continue;
            }
            // mirrors BuildsFormFields::createFormField()'s own type detection
            $typeName = ltrim(stristr((string)$field->type, 'Fieldtype'));
            if (in_array($typeName, ['FieldtypeImage', 'FieldtypeCroppableImage3'], true)) {
                return $field;
            }
        }

        return null;
    }
}
