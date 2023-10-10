<?php
declare(strict_types=1);

namespace FrontendLoginRegister;

// checked 27.3

/*
 * Form to request your login data, if you have forgotten it
 * Sends an email with a link to create a new password if the account is verified or
 * sends an email with an activation link if the account is not verified til now.
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
use ProcessWire\WireMail;
use ProcessWire\WirePermissionException;

class ForgotLogindataPage extends FrontendLoginRegisterPages
{

    /**
     * @throws WireException
     * @throws Exception
     */
    public function __construct(string $id = 'forgotlogindata-form')
    {

        parent::__construct($id);

        // if user is logged in -> no need to be here -> lets redirect to the homepage
        $this->redirectToHomepage();

        // default settings
        $this->setMaxAttempts(5);
        $this->setMinTime(2);
        $this->setMaxTime(600);
        $this->setSuccessMsg($this->_('If the email address supplied was found, an email with further instructions will be sent to you. Please check your email account within the next few minutes, including any junk or spam folders.'));
        $this->setSubmitWithAjax($this->useAjax);

        // email field
        $email = new Email('email');
        $this->add($email);

        // button object
        $button = new Button('submit');
        $buttonText = $this->_('Recover password');
        // change button text, if username is used to log in
        if ($this->loginregisterConfig['input_selectlogin'] == 'username') {
            $buttonText = $this->_('Request login data');
        }
        $button->setAttribute('value', $buttonText);
        $this->add($button);
    }

    /**
     * @return string
     * @throws WireException
     */
    public function __toString():string
    {
        return $this->render();
    }

    /**
     * Render the form
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    public function render():string
    {
        if ($this->isValid()) {
            $content = '';

            // create the recovery code
            $recoveryCode = $this->createQueryCode();

            // get the user by email
            $user = $this->wire('users')->get('email=' . $this->getValue('email')); // get the user object

            // send only an email if the email address is stored in the database
            if ($user->id != 0) {

                if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                    // get the id of the user language as stored inside the db
                    $this->stored_user_lang = $this->getSavedUserLanguage($user);

                    // change user language to the stored user language placeholder in the stored user language
                    $this->user->setLanguage($this->stored_user_lang);
                }

                // add placeholders !!important!!
                $this->createGeneralPlaceholders();

                //check if account was activated or not
                if ($user->fl_activation) {
                    // user has not activated his account til now - lets send a reminder email to activate the account

                    // send the email with the stored user language
                    $reminder_mail_sent = $this->sendReminderMail($user);

                    if ($reminder_mail_sent) {
                        // overwrite alert message
                        $this->getAlert()->setCSSClass('alert_warningClass')->setText($this->_('It seems that you have already registered for an account,  but your account is still pending approval. To activate your account, please follow the instructions inside the mail that have been sent to you. After that you can create your new login data.'));
                    }

                } else {
                    // email the user in his stored user language, which contains a link to set new login data

                    // create re
                    //quest text depending on login settings
                    if ($this->loginregisterConfig['input_selectlogin'] == 'username') {
                        $requestText = $this->_('login data (password and/or username)');
                    } else {
                        $requestText = $this->_('password');
                    }

                    // create placeholder variables
                    $this->setMailPlaceholder('logindata', $requestText);
                    $resetText = ($this->loginregisterConfig['input_selectlogin'] == 'username') ? $this->_('login data') : $this->_('password');
                    $this->setMailPlaceholder('resettext', $resetText);
                    $this->setMailPlaceholder('recoverPasswordlink',
                        $this->createCodeLink('fl_recoverylogindatapage', $recoveryCode));

                    $m = new WireMail();
                    $m->to($user->email);
                    $m->from($this->loginregisterConfig['input_email']);
                    $this->setSenderName($m);
                    $m->subject(sprintf($this->_('Action required to reset your %s'), $resetText));
                    $m->title(sprintf($this->_('Create a new %s'), $requestText));

                    if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                        $text = $this->getLangValueOfConfigField('input_passwordforgottentext',
                            $this->loginregisterConfig, $this->stored_user_lang->id);
                    } else {
                        $text = $this->loginregisterConfig['input_passwordforgottentext'];
                    }
                    $body = $text . $this->___generateNoReplyText();

                    $m->bodyHTML($body);
                    $m->mailTemplate($this->loginregisterConfig['input_emailTemplate']);
                    $mail_sent = $m->send();

                    if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                        // set back the language to the site language
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
            $content = $this->wire('page')->body;
        }
        // render the form on the frontend
        $content .= parent::render();
        return $content;
    }

}
