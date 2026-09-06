<?php

declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Class for requesting a deletion link for the user account
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: DeleteRequestPage.php
 * Created: 06.07.2022
 */

use Exception;
use FrontendForms\Email;
use FrontendForms\Button as Button;
use FrontendForms\Password as Password;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

class DeleteRequestPage extends FrontendLoginRegisterPages
{
    /**
     * Every form must have an ID, so let's add it via the constructor
     * @param string $id
     * @throws WireException
     * @throws WirePermissionException
     * @throws Exception
     */
    public function __construct(string $id = 'deleteaccount-form')
    {
        parent::__construct($id);

        // if user is guest -> not allowed to be here -> let's redirect to the homepage
        // redirect only takes place if the page is not publicly reachable
        if (!$this->loginregisterConfig['input_deleteProfile']) {
            $this->redirectToHomepage();
        } else {
            if (!$this->loginregisterConfig['input_publicDeletion']) {
                $this->redirectToHomepage(true);
            }
        }

        // default settings
        $this->setMaxAttempts(5);
        $this->setMinTime(2);
        $this->setMaxTime(3600);
        $this->setSuccessMsg($this->_('A link to complete your account deletion has been sent to your email address.'));
        $this->setSubmitWithAjax($this->useAjax);
        // disable CAPTCHA if user is logged in
        if ($this->user->isLoggedin()) {
            $this->disableCaptcha();
        }

        // show email field only if a user is not logged in and the deletion page is publicly reachable
        if ($this->loginregisterConfig['input_publicDeletion'] && (!$this->user->isLoggedin())) {
            // user email field
            $email = new Email('email');
            $email->setRule('required');
            $this->add($email);
        }

        // password field
        // sanitizers added: text
        // validation rules added: required, checkPasswordOfUser
        $pass = new Password('pass');

        // remove unnecessary validation rules
        $pass->removeRule('safePassword');
        $pass->removeRule('meetsPasswordConditions');

        // remove password requirement text on this form
        $pass->showPasswordRequirements(false);

        // check for password and email match
        if ($this->loginregisterConfig['input_publicDeletion'] && (!$this->user->isLoggedin())) {
            $pass->setRule('matchEmail', 'email');
        } else {
            $pass->setRule('checkPasswordOfUser', $this->user);
        }

        $pass->showPasswordToggle(!$this->loginregisterConfig['input_hide_passwordtoggle']);
        $this->add($pass);

        // button object
        $button = new Button('submit');
        $button->setAttribute('value', $this->_('Request the deletion link'));
        $this->add($button);
    }

    /**
     * Return the rendered form markup
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Render the form markup
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    public function render(): string
    {
        $content = '';
        if (!$this->getSubmitWithAjax()) {
            $content .= $this->prependBody();
        }
        if ($this->isValid()) {
            // determine the account the deletion link is for: the logged-in user, or - on the publicly
            // reachable variant of this form - whoever the submitted (and password-verified) email belongs to
            if ($this->user->isLoggedin()) {
                $targetUser = $this->user;
            } else {
                $targetUser = $this->wire('users')->get(
                    'email=' . $this->wire('sanitizer')->selectorValue($this->getValue('email'))
                );
            }

            if (!$targetUser->id) {
                // should not happen, since the matchEmail rule already validated the password against this
                // email during isValid() - guarded here defensively instead of failing on the type check below
                $this->generateEmailSentErrorAlert();
            } else {
                if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                    // get the ID of the user language as stored inside the db
                    $this->stored_user_lang = $this->getSavedUserLanguage($targetUser);

                    // Change the language on $this->user, NOT on $targetUser (the account the link is
                    // emailed to). This looks backwards, but $this->user is the actual current-visitor
                    // User object (see Form::initUserAndLanguage(): $this->user = $this->wire('user')),
                    // and ProcessWire's _() translation calls below (subject, title) resolve against
                    // wire('user')->language - not against an arbitrary other User object. Switching
                    // $targetUser's language instead would silently leave those strings untranslated in
                    // the recipient's language, and - worse - would get persisted to the wrong value
                    // when $targetUser->save() runs further down, since it is reset to the site language
                    // afterward.
                    $this->user->setLanguage($this->stored_user_lang);
                }

                // add placeholders !!important!!
                $this->createGeneralPlaceholders();

                // generate a random code
                $deleteCode = $this->createQueryCode(); // create the deletion code

                // send an email with the deletion link to the user in the stored user language, not the site language
                $mail_sent = $this->sendLocalizedMail(
                    $targetUser,
                    'input_deleteaccounttext',
                    ['deleteaccountlink' => $this->createCodeLink('fl_deleteaccountpage', $deleteCode)],
                    $this->_('Action required to delete your account'),
                    $this->_('Please click the link inside the mail')
                );

                if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                    // set the language on $this->user (see the comment above) back to the site language
                    $this->user->setLanguage($this->site_language_id);
                }

                // save user data only if mail was sent successfully
                if ($mail_sent) {
                    // grab the target user and store the random string inside the recovery code input
                    $targetUser->of(false);
                    $targetUser->fl_deleteaccount = $deleteCode; // save the code in the db
                    $targetUser->fl_deleteaccountdatetime = time(); // save the datetime string in the db
                    if (!$targetUser->save()) {
                        // output warning message that a problem occurs during saving of user
                        $this->savingUserProblemAlert();
                    }
                    $targetUser->of(true);
                } else {
                    // output an error message that the mail could not be sent
                    $this->generateEmailSentErrorAlert();
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
