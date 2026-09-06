<?php

declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Form to request your login data, if you have forgotten it
 * Sends an email with a link to create a new password if the account is verified or
 * sends an email with an activation link if the account is not verified till now.
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: ForgotLogindataPage.php
 * Created: 06.07.2022
 */

use Exception;
use FrontendForms\Button as Button;
use FrontendForms\Email as Email;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

class ForgotLogindataPage extends FrontendLoginRegisterPages
{
    // NOTE: $fl_recoverylogindata and $fl_recoverylogindatadatetime below are never read or
    // written anywhere in this class - the actual recovery code/timestamp fields live on the
    // User page object ($user->fl_recoverylogindata / $user->fl_recoverylogindatadatetime, set
    // in render() below), not on these class properties. Left untouched here since removing
    // them is a separate, behavior-neutral cleanup decision - candidates for removal in a
    // follow-up.
    protected string $fl_recoverylogindata = '';
    protected int|string $fl_recoverylogindatadatetime = '';

    /**
     * Every form must have an ID, so let's add it via the constructor
     * @param string $id
     * @throws WireException
     * @throws WirePermissionException
     * @throws Exception
     */
    public function __construct(string $id = 'forgotlogindata-form')
    {
        parent::__construct($id);

        // if a user is logged in -> no need to be here -> let's redirect to the homepage
        $this->redirectToHomepage();

        // default settings
        $this->setMaxAttempts(5);
        $this->setMinTime(2);
        $this->setMaxTime(600);
        $this->setSuccessMsg($this->_('If the email address supplied was found, an email with further instructions will be sent to you. Please check your email account within the next few minutes, including any junk or spam folders.'));
        $this->setSubmitWithAjax($this->useAjax);

        // email field
        $email = new Email('email');
        $email->setRule('required');
        $this->add($email);

        // button object
        $button = new Button('submit');
        $buttonText = $this->_('Recover password');
        // change button text if username is used to log in
        if ($this->loginregisterConfig['input_selectlogin'] == 'username') {
            $buttonText = $this->_('Request login data');
        }
        $button->setAttribute('value', $buttonText);
        $this->add($button);
    }

    /**
     * Return the rendered form markup
     * @return string
     * @throws \DOMException
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Render the form
     * @return string
     * @throws \DOMException
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     */
    public function render(): string
    {
        $content = '';
        if (!$this->getSubmitWithAjax()) {
            $content .= $this->prependBody();
        }

        if ($this->isValid()) {
            // create the recovery code
            $recoveryCode = $this->createQueryCode();

            // get the user by email
            $user = $this->wire('users')->get(
                'email=' . $this->wire('sanitizer')->selectorValue($this->getValue('email'))
            ); // get the user object

            // send only an email if the email address is stored in the database
            if ($user->id != 0) {
                if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                    // get the ID of the user language as stored inside the db
                    $this->stored_user_lang = $this->getSavedUserLanguage($user);

                    // Change the language on $this->user, NOT on $user (the account the mail is being
                    // sent to). This looks backwards, but $this->user is the actual current-visitor
                    // User object (see Form::initUserAndLanguage(): $this->user = $this->wire('user')),
                    // and ProcessWire's _() translation calls used further down (subject, title)
                    // resolve against wire('user')->language - not against an arbitrary other User
                    // object like $user. Switching $user's language instead would silently leave those
                    // strings untranslated in the recipient's language, and - worse - would get
                    // persisted to the wrong value when $user->save() runs further down, since it is
                    // reset to the site language afterward.
                    $this->user->setLanguage($this->stored_user_lang);
                }

                // add placeholders !!important!!
                $this->createGeneralPlaceholders();

                // check if the account was activated or not
                if ($user->fl_activation) {
                    // user has not activated his account till now - let's send a reminder email to activate the account

                    // send the email with the stored user language
                    $reminder_mail_sent = $this->sendReminderMail($user);

                    if ($reminder_mail_sent) {
                        // overwrite alert message
                        $this->setAlert('alert_warningClass', $this->_('It seems that you have already registered for an account,  but your account is still pending approval. To activate your account, please follow the instructions inside the mail that have been sent to you. After that you can create your new login data.'));
                    }
                } else {
                    // email the user in his stored user language, which contains a link to set new login data
                    if ($this->loginregisterConfig['input_selectlogin'] == 'username') {
                        $requestText = $this->_('the login data (password and/or username)');
                        $resetText = $this->_('the login data');
                    } else {
                        $requestText = $this->_('the password');
                        $resetText = $this->_('the password');
                    }

                    $mail_sent = $this->sendLocalizedMail(
                        $user,
                        'input_passwordforgottentext',
                        [
                            'logindata' => $requestText,
                            'resettext' => $resetText,
                            'recoverPasswordlink' => $this->createCodeLink('fl_recoverylogindatapage', $recoveryCode),
                        ],
                        sprintf($this->_('Action required to reset your %s'), $resetText),
                        sprintf($this->_('Create a new %s'), $requestText)
                    );

                    if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                        // set the language on $this->user (see the comment above) back to the site language
                        $this->user->setLanguage($this->site_language_id);
                    }

                    // save user data only if mail was sent successfully
                    if ($mail_sent) {
                        // grab the user and store the random string inside the recovery code input
                        $user->of(false);
                        $user->fl_recoverylogindata = $recoveryCode; // save the code in the db
                        $user->fl_recoverylogindatadatetime = time(); // save the datetime string in the db
                        if (!$user->save()) {
                            // there was a problem saving the user
                            $this->savingUserProblemAlert();
                        }
                        $user->of(true);
                    } else {
                        // output an error message that the mail could not be sent
                        $this->generateEmailSentErrorAlert();
                    }
                }
            }
        } else {
            if ($this->getSubmitWithAjax()) {
                $content .= $this->prependBody();
            }
        }
        // render the form on the frontend
        return $content . parent::render();
    }
}
