<?php

declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Trait bundling the mail-sending concern: resolving the configured mail text in the user's stored
 * language, applying placeholders, building the WireMail instance with sender/subject/title/body/
 * template, and sending it - plus the reminder and deletion-confirmation mails, and the small sender
 * helpers (name/email/no-reply footer) they and sendLocalizedMail() share.
 *
 * sendLocalizedMail() covers the single-text case (activation, password reset, TFA code, ...) that
 * was duplicated across LoginPage, RegisterPage, DeleteRequestPage and ForgotLogindataPage. The
 * caller is still responsible for switching the user language beforehand (and back afterward) and
 * for calling createGeneralPlaceholders().
 *
 * sendReminderMail() and sendDeletionConfirmationMail() build several placeholders of their own
 * before sending, so they are not routed through sendLocalizedMail() - they were moved here
 * unchanged, together with the rest of the mail-related methods from FrontendLoginRegisterPages.
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: SendsLocalizedMail.php
 */

use DateTime;
use FrontendForms\Form;
use ProcessWire\User;
use ProcessWire\WireException;
use ProcessWire\WireMail;
use ProcessWire\WirePermissionException;
use function ProcessWire\wirePopulateStringTags;

trait SendsLocalizedMail
{
    /**
     * Build and send one localized transactional mail
     * @param User $user - the recipient
     * @param string $textConfigKey - module config key holding the mail body text, fe 'input_passwordforgottentext'
     * @param array $placeholders - additional mail placeholders as [name => value], set before the body is resolved
     * @param string $subject
     * @param string $title
     * @return bool - true if the mail was sent successfully
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     */
    protected function sendLocalizedMail(
        User $user,
        string $textConfigKey,
        array $placeholders,
        string $subject,
        string $title
    ): bool {
        foreach ($placeholders as $name => $value) {
            $this->setMailPlaceholder($name, $value);
        }

        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            $text = $this->getLangValueOfConfigField(
                $textConfigKey,
                $this->loginregisterConfig,
                $this->stored_user_lang->id
            );
        } else {
            $text = $this->loginregisterConfig[$textConfigKey];
        }

        $m = $this->newMailInstance($this->loginregisterConfig['input_mailmodule']);
        $m->to($user->email);
        $this->setSenderEmail($m);
        $this->setSenderName($m);
        $m->subject($subject);
        $m->title($title);

        $body = $text . $this->generateNoReplyText();

        // Add the HTML body property to the Mail object
        Form::setBody($m, $body, $this->loginregisterConfig['input_mailmodule']);

        $m->mailTemplate($this->loginregisterConfig['input_emailTemplate']);

        return (bool)$m->send();
    }

    /**
     * Send a reminder mail to the user if the account is not activated
     * @param User $user
     * @return bool
     * @throws WireException
     * @throws WirePermissionException
     * @throws \DOMException
     * @throws \DateMalformedStringException
     */
    protected function sendReminderMail(User $user): bool
    {
        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            // get the user language object as stored inside the db
            $this->stored_user_lang = $this->getSavedUserLanguage($user);

            // change user language to the stored user language placeholder in the stored user language
            $this->user->setLanguage($this->stored_user_lang);
        }

        // add placeholders !!important!!
        $this->createGeneralPlaceholders();

        $days_to_delete = $this->daysToDelete($user);

        $date_to_delete_ts = (new DateTime('NOW'))->modify('+' . $days_to_delete . ' days')->getTimestamp();

        // create placeholder variables

        // 1) registration date
        $this->setMailPlaceholder('registrationdate',
            $this->wire('datetime')->date($this->getDateFormat($user), $user->created));

        // 2) deletion date
        $this->setMailPlaceholder('deletedate',
            $this->wire('datetime')->date($this->getDateFormat($user), $date_to_delete_ts));

        // 3) verification link
        $this->setMailPlaceholder('verificationlink', $this->createActivationLink($user));

        // 4) not registered link
        $this->setMailPlaceholder('notregisteredlink', $this->createNotRegisteredLink($user));

        $m = $this->newMailInstance($this->loginregisterConfig['input_mailmodule']);
        $m->to($user->email);
        $this->setSenderEmail($m);
        $this->setSenderName($m);
        $m->subject($this->_('Action required to activate your account'));
        $m->title($this->_('Have you forgotten to verify your account?'));

        // set placeholder for title
        $this->setMailPlaceholder('title', $m->title);

        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            $text = $this->getLangValueOfConfigField('input_remindertext', $this->loginregisterConfig,
                $this->stored_user_lang->id);
        } else {
            $text = $this->loginregisterConfig['input_remindertext'];
        }

        $body = $this->generateEmailPreHeader($m) . $text . $this->generateNoReplyText();
        $body = wirePopulateStringTags($body, $this->getMailPlaceholders(), ['tagOpen' => '[[', 'tagClose' => ']]']);
        $this->setMailPlaceholder('body', $body);
        $m->bodyHTML($body);
        $m->mailTemplate($this->loginregisterConfig['input_emailTemplate']);
        $this->includeMailTemplate($m);
        $m->mailTemplate('none'); // important to prevent double inclusion of HTML template on hooks

        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            // set back the language to the site language
            $this->user->setLanguage($this->site_language_id);
        }

        return (bool)$m->send();
    }

    /**
     * Send an email to the user that he has to confirm the deletion of his account
     * @param \ProcessWire\User $user
     * @return bool
     * @throws \DOMException
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     */
    protected function sendDeletionConfirmationMail(User $user): bool
    {
        if (!$this->loginregisterConfig['input_prevent_send_deletion_email']) {
            if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                // get the ID of the user language as stored inside the db
                $this->stored_user_lang = $user->language;

                // change user language to the stored user language placeholder in the stored user language
                $this->user->setLanguage($this->stored_user_lang);
            }

            // add placeholders !!important!!
            $this->createGeneralPlaceholders();

            // create placeholders
            $this->setMailPlaceholder('registrationdate',
                $this->wire('datetime')->date($this->getDateFormat($user), $user->created));
            $this->setMailPlaceholder('registerurl', $this->wire('pages')->get('template=fl_registerpage')->httpUrl);

            // create mail
            $m = $this->newMailInstance($this->loginregisterConfig['input_mailmodule']);
            $m->to($user->email);
            $this->setSenderEmail($m);
            $this->setSenderName($m);
            $m->subject($this->_('Your account has been deleted'));
            $m->title($this->_('Good bye!'));

            // set placeholder for title
            $this->setMailPlaceholder('title', $m->title);

            if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                $text = $this->getLangValueOfConfigField('input_deletion_confirmation', $this->loginregisterConfig,
                    $this->stored_user_lang->id);
            } else {
                $text = $this->loginregisterConfig['input_deletion_confirmation'];
            }

            $body = $this->generateEmailPreHeader($m) . $text . $this->generateNoReplyText();
            $body = wirePopulateStringTags($body, $this->getMailPlaceholders(),
                ['tagOpen' => '[[', 'tagClose' => ']]']);

            $this->setMailPlaceholder('body', $body);
            $m->bodyHTML($body);
            $m->mailTemplate($this->loginregisterConfig['input_emailTemplate']);
            $this->includeMailTemplate($m);
            $m->mailTemplate('none'); // important to prevent double inclusion of HTML template on hooks

            if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                // set back the language to the site language
                $this->user->setLanguage($this->site_language_id);
            }

            return (bool)$m->send();
        }
        return true;
    }

    /**
     * Create a centered "do not replay" info at the footer of the email body
     * @return string
     */
    protected function ___generateNoReplyText(): string
    {
        return '<br><table class="footer" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-top: 1px solid #ddd;">
              <tr>
                <td class="center" align="center" valign="top">
                  <center>
                    <table class="container">
                        <tr>
                          <td><br>
                            [[DONOTREPLAYVALUE]]
                          </td>
                      </tr>
                    </table>
                  </center>
                </td>
              </tr>
            </table>';
    }

    /**
     * @throws WireException
     */
    protected function setLanguageMailValue(WireMail $mail, string $fieldName): string|null
    {
        $value = null;
        if ($this->loginregisterConfig[$fieldName]) {
            $value = $this->loginregisterConfig[$fieldName];
            // set multi-language value if language support is installed
            if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                $value = $this->getLangValueOfConfigField($fieldName, $this->loginregisterConfig,
                    $this->user->language->id);
            }
        }
        return $value;
    }

    /**
     * Set the mail sender name to the mail
     * @param $mail
     * @return void
     * @throws WireException
     */
    protected function setSenderName($mail): void
    {
        $senderName = $this->setLanguageMailValue($mail, 'input_sender');
        if (is_null($senderName)) {
            $senderName = 'noreply@' . $this->wire('config')->httpHost;
        }
        $mail->fromName($senderName);
    }

    /**
     * Set the mail sender email to the mail
     * @param $mail
     * @return void
     * @throws \ProcessWire\WireException
     */
    protected function setSenderEmail($mail): void
    {
        // Set from value depending on settings
        switch ($this->loginregisterConfig['input_mailmodule']) {
            case('WireMailSmtp'):
                break;
            default:
                $senderEmail = $this->setLanguageMailValue($mail, 'input_email');
                if (is_null($senderEmail)) {
                    $senderEmail = 'noreply@' . $this->wire('config')->httpHost;
                }
                $mail->from($senderEmail);
        }
    }
}
