<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

use FrontendLoginRegister\RegisterPage;
use ProcessWire\WireMail;

require_once __DIR__ . '/bootstrap.php';

/**
 * Real, fully-constructed Form instance for exercising SendsLocalizedMail's sender-resolution
 * helpers (setSenderEmail(), setSenderName(), and the setLanguageMailValue() both share) and the
 * static generateNoReplyText() footer builder against a live ProcessWire instance and the real
 * module configuration - without calling any of the mail-sending methods themselves
 * (sendLocalizedMail(), sendReminderMail(), sendDeletionConfirmationMail()), which all end in a
 * real WireMail::send() call.
 *
 * RegisterPage::__construct() redirects away (and exits the process!) if a user IS logged in, so
 * this harness must only ever be constructed as a guest - fine here, since none of the methods
 * exercised depend on being logged in.
 */
class SendsLocalizedMailHarness extends RegisterPage
{
    public function callSetSenderEmail(WireMail $mail): void
    {
        $this->setSenderEmail($mail);
    }

    public function callSetSenderName(WireMail $mail): void
    {
        $this->setSenderName($mail);
    }

    public function callSetLanguageMailValue(WireMail $mail, string $fieldName): ?string
    {
        return $this->setLanguageMailValue($mail, $fieldName);
    }

    public function callGenerateNoReplyText(): string
    {
        return $this->generateNoReplyText();
    }
}
