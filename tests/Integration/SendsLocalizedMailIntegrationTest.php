<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/SendsLocalizedMailHarness.php';

use ProcessWire\WireMail;

/**
 * Integration coverage for SendsLocalizedMail's sender-resolution helpers, against the real module
 * configuration. Deliberately does NOT cover sendLocalizedMail(), sendReminderMail() or
 * sendDeletionConfirmationMail() themselves - all three end in a real WireMail::send() call, which
 * (depending on the configured mail module, e.g. a real WireMailSmtp account) could actually
 * dispatch an email rather than being a harmless local no-op, so they are out of scope here.
 */
final class SendsLocalizedMailIntegrationTest extends IntegrationTestCase
{
    public function testSetSenderEmailUsesTheNoReplyAddressUnlessWireMailSmtpIsConfigured(): void
    {
        $config = $this->wire('modules')->getConfig('FrontendLoginRegister');
        $mailModule = $config['input_mailmodule'] ?? 'none';

        if ($mailModule === 'WireMailSmtp') {
            // setSenderEmail() deliberately leaves the "from" address alone for WireMailSmtp,
            // trusting that module's own account configuration instead - nothing of this trait's
            // own logic to assert here.
            $this->markTestSkipped(
                'Module is configured to use WireMailSmtp, whose own "from" address '
                . 'setSenderEmail() intentionally does not touch.'
            );
        }

        $harness = new SendsLocalizedMailHarness();
        $mail = new WireMail();
        $harness->callSetSenderEmail($mail);

        // FrontendLoginRegisterPages::__construct() always overwrites the module's "input_email"
        // config value with "noreply@<httpHost>" (see its own comment: "important to send mails
        // from shared hosts"), so this is the only value setSenderEmail() can ever produce here,
        // regardless of what might otherwise be configured.
        $this->assertSame('noreply@' . $this->wire('config')->httpHost, $mail->from);
    }

    public function testSetSenderNameFallsBackToNoReplyAddressWhenNoCustomSenderIsConfigured(): void
    {
        $harness = new SendsLocalizedMailHarness();

        $mail = new WireMail();
        $harness->callSetSenderName($mail);

        // Replicate setSenderName()'s own fallback rule using the real, resolved config value
        // (setLanguageMailValue() already applies the multi-language lookup when LanguageSupport
        // is installed), so this assertion holds whatever the real site actually has configured -
        // a custom sender name, a per-language override, or nothing at all.
        $expected = $harness->callSetLanguageMailValue(new WireMail(), 'input_sender')
            ?? 'noreply@' . $this->wire('config')->httpHost;

        $this->assertSame($expected, $mail->fromName);
    }
}
