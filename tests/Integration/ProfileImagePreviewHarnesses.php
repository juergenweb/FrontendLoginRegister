<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

use FrontendLoginRegister\ProfilePage;
use FrontendLoginRegister\RegisterPage;

require_once __DIR__ . '/bootstrap.php';

/**
 * Thin, real, fully-constructed Form instances for exercising
 * HandlesProfileImage::createProfileImagePreview() against a live ProcessWire instance.
 *
 * Unlike CreatesVerificationLinksHarness, createProfileImagePreview() reads $this->getID() (from
 * the FrontendForms\Form/Tag base class) and needs $this->image_fields to actually be populated by
 * the real field-building logic in BuildsFormFields::createFormField() - both of which only exist
 * once a form has gone through its normal, full constructor. So rather than a bare WireData host,
 * these harnesses subclass the real page classes and just add public pass-throughs to the
 * protected method and property under test.
 *
 * RegisterPage::__construct() redirects away (and exits!) if a user IS logged in, so
 * RegisterPageProfileImagePreviewHarness must only ever be constructed as a guest.
 * ProfilePage::__construct() redirects away (and exits!) if the user is a guest, so
 * ProfilePageProfileImagePreviewHarness must only ever be constructed while logged in.
 */
class RegisterPageProfileImagePreviewHarness extends RegisterPage
{
    public function callCreateProfileImagePreview(?string $fieldname = null): string
    {
        return $this->createProfileImagePreview($fieldname);
    }

    /** @return string[] */
    public function getImageFieldNames(): array
    {
        return $this->image_fields;
    }
}

class ProfilePageProfileImagePreviewHarness extends ProfilePage
{
    public function callCreateProfileImagePreview(?string $fieldname = null): string
    {
        return $this->createProfileImagePreview($fieldname);
    }

    /** @return string[] */
    public function getImageFieldNames(): array
    {
        return $this->image_fields;
    }
}
