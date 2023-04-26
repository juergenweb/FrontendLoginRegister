<?php
declare(strict_types=1);

namespace FrontendLoginRegister;
//checked 27.3
/*
 * Class for creating a simple register form with at least username/email and password.
 * Can contain more fields if they were set in the backend config.
 * Sends an email with an activation link to the user
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: RegisterPage.php
 * Created: 06.07.2022
 */

use FrontendForms\Button as Button;
use FrontendForms\Privacy as Privacy;
use ProcessWire\TfaEmail;
use ProcessWire\User;
use ProcessWire\WireException;
use ProcessWire\WireMail;
use ProcessWire\WirePermissionException;

class RegisterPage extends FrontendLoginRegisterPages
{

    /**
     * @throws WireException
     */
    public function __construct(string $id = 'register-form')
    {

        parent::__construct($id);

        // if user is logged in -> no need to be here -> lets redirect to the homepage
        $this->redirectToHomepage();

        // set default form values
        $this->setMaxAttempts(5);
        $this->setMinTime(3);
        $this->setMaxTime(3600);

        // add the various form fields dynamically depending on the settings in the backend
        $this->createFormFields('input_registration', $this);

        // add checkbox to accept privacy policy
        $privacy = new Privacy('privacy');
        $this->add($privacy);

        // add the button
        $button = new Button('submit');
        $button->setAttribute('value', $this->_('Register'));
        $this->add($button);

    }

    /**
     * @return string
     * @throws WireException
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * Method to check if activation code is still stored inside the db by a given email address
     * @param string $email
     * @return User|null
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function getUserByActivationCode(string $email):User|null
    {
        $user = $this->wire('users')->get('email=' . $email);
        // check if activation code exists
        if (($user->id > 0) && ($user->fl_activation)) {
            return $user;
        }
        return null;
    }

    /**
     * Calculate the date and time for user deletion
     * @param bool $timestamp
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function getDeleteDateTime(bool $timestamp = false):string
    {
        $current_date = date('Y-m-d');
        $current_time = date('H:i');

        $new_date = date('Y-m-d',
            strtotime($current_date . ' + ' . $this->loginregisterConfig['input_delete'] . ' days'));
        if ($timestamp) {
            return $this->wire('datetime')->stringToTimestamp($new_date . ' ' . $current_time, 'Y-m-d H:i');
        } else {
            return $new_date . ' ' . $current_time;
        }
    }

    /**
     * Render the form
     * @return string
     * @throws WireException
     */
    public function render():string
    {

        if ($this->isValid()) {

            //get the form values
            $email = $this->getValue('email');
            $pass = $this->getValue('pass');

            $activationCode = $this->createQueryCode();// create the activation code

            // create the new user
            $newUser = new User();
            $newUser->of(false);

            // set values for all fields inside the form
            $this->setFormFieldValues($newUser);

            // save the activation code
            $newUser->fl_activation = $activationCode;

            // add all roles depending on configuration settings to the new user
            foreach ($this->loginregisterConfig['input_roles'] as $role) {
                $newUser->addRole($role);
            }

            if ($newUser->save()) {

                // get the id of the user language as stored inside the db
                $this->stored_user_lang = $this->getSavedUserLanguage($newUser);

                // change user language to the stored user language placeholder in the stored user language
                $this->user->setLanguage($this->stored_user_lang);

                // add placeholders !!important!!
                $this->createGeneralPlaceholders();

                // if TFA email is enabled - add it to the user
                if (($this->loginregisterConfig['input_tfa']) && ($this->wire('modules')->isInstalled('TfaEmail'))) {
                    $tfa = new TfaEmail();
                    $tfa->autoEnableUser($newUser);
                }
                // create placeholders
                $this->setMailPlaceholder('currentdatetimevalue',
                    $this->wire('datetime')->date($this->getDateFormat($newUser), time()));
                $this->setMailPlaceholder('verificationlink', $this->createActivationLink($newUser));
                $this->setMailPlaceholder('notregisteredlink', $this->createNotRegisteredLink($newUser));

                // send an email with the activation link to the user
                $m = new WireMail();
                $m->to($newUser->email);
                $m->from($this->loginregisterConfig['input_email']);
                $this->setSenderName($m);
                $m->subject($this->_('Action required to activate your account'));
                $m->title($this->_('Please click the link to verify your registration'));
                $m->bodyHTML($this->getLangValueOfConfigField('input_activationtext', $this->loginregisterConfig,
                    $this->stored_user_lang->id).$this->___generateNoReplyText());
                $m->mailTemplate($this->loginregisterConfig['input_emailTemplate']);

                $activation_mail_sent = $m->send();

                // set back the language to the site language
                $this->user->setLanguage($this->site_language_id);

                if ($activation_mail_sent) {

                    // add additional texts for the success alert
                    $noEmailText = $this->_('If you do not see the email in a few minutes, check your “junk mail” folder or “spam” folder.');
                    $registerNewText = $this->_('If you still have not received an email, please fill out the registration form again to receive a new activation link.');

                    $successMsg = $this->_('Your account has been created successfully. To activate your account, please follow the instructions inside the mail that has been sent to you.');
                    $this->getAlert()->setText($successMsg . '<br>' . $noEmailText . '<br>' . $registerNewText);
                } else {
                    // output an error message that the mail could not be sent
                    $this->generateEmailSentErrorAlert();
                }

            } else {
                $this->savingUserProblemAlert();
            }
            $newUser->of(true);
        } else {
            // form is not valid, so check if email address exists but is not verified
            // or in other words: check if user has been registered twice and has not confirmed his first registration
            $email_field_name = $this->getAttribute('id') . '-email';

            // check if there is an error inside the email field
            if (array_key_exists($email_field_name, $this->formErrors)) {
                $email = $this->getValue($email_field_name);

                // run only if an email address was entered
                if ($email) {

                    // get the user
                    $user = $this->getUserByActivationCode($email);
                    if (!is_null($user)) {

                        // send reminder mail
                        if ($this->sendReminderMail($user)) {
                            // overwrite alert message
                            $this->getAlert()->setCSSClass('alert_warningClass')->setText($this->_('It seems that you have already registered before but have forgotten to activate your account. To activate your account, please follow the instructions inside the mail that have been sent to you.'));
                            // do not display the form
                            $this->showForm = false;
                        }
                    }
                }
            }


        }
        // render the form on the frontend
        $content = $this->wire('page')->body;
        $content .= parent::render();
        return $content;
    }
}
