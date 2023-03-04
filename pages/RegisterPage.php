<?php
declare(strict_types=1);

namespace FrontendLoginRegister;

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
use ProcessWire\User as User;
use ProcessWire\WireException;
use ProcessWire\WireMail;
use ProcessWire\WirePermissionException;

class RegisterPage extends FrontendLoginRegisterPages
{

    use activation;

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
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function getActivationCode(string $email): string
    {
        $user = $this->wire('users')->get('email=' . $email);
        $code = '';
        // check if activation code exists
        if ($user->id > 0) {
            $code = $user->fl_activation;
        }
        return $code;
    }

    /**
     * Calculate the date and time for user deletion
     * @param bool $timestamp
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function getDeleteDateTime(bool $timestamp = false): string
    {
        $current_date = date('Y-m-d');
        $current_time = date('H:i');

        $new_date = date('Y-m-d', strtotime($current_date. ' + '.$this->input_delete.' days'));
        if($timestamp){
            return $this->wire('datetime')->stringToTimestamp($new_date.' '.$current_time, 'Y-m-d H:i');
        } else {
            return $new_date.' '.$current_time;
        }
    }

    /**
     * Render the form
     * @return string
     * @throws WireException
     */
    public function render(): string
    {

        if ($this->isValid()) {

            //get and generate values first
            $email = $this->getValue('email');
            $pass = $this->getValue('pass');
            $noEmailText = $this->_('If you do not see the email in a few minutes, check your “junk mail” folder or “spam” folder.');
            $registerNewText = $this->_('If you still have not received an email, please fill out the registration form again to receive a new activation link.');

                $activationCode = $this->createQueryCode();// create the activation code

                // create the new user
                $newUser = new User();
                $newUser->of(false);

                // save username if login with username and password is selected
                if ($this->input_selectlogin == 'username') {
                    $newUser->name = $this->wire('sanitizer')->pageName($this->getValue('username'));
                }

                $newUser->email = $email; // save user email
                $newUser->pass = $pass; // save password

                // save user language only on multi-language site
                if($this->wire('modules')->isInstalled('LanguageSupport')) {
                    $language = $this->wire('input')->post('register-form-language') !== null ? $this->wire('input')->post('register-form-language') : $this->wire('user')->language->id;
                    $newUser->language = $language; // save user language
                }

                $newUser->fl_activation = $activationCode; // save the activation code

                // add all roles depending on configuration settings to the new user
                foreach ($this->input_roles as $role) {
                    $newUser->addRole($role);
                }

                if ($newUser->save()) {

                    // if TFA email is enabled - add it to the user
                    if($this->input_tfa){
                        $tfa = new TfaEmail();
                        $tfa->autoEnableUser($newUser);
                    }

                    // set the language for the mail according to the user language on multi-language site
                    if($this->wire('languages')){
                        $this->wire('languages')->setLanguage($newUser->language->name);
                    }

                    // create placeholders
                    $this->setMailPlaceholder('daystodelete', (string)$this->input_delete);
                    $this->setMailPlaceholder('deletedate', $this->wire('datetime')->date($this->getDateFormat($newUser), $newUser->fl_activationdatetime));
                    $this->setMailPlaceholder('verificationlink', $this->createActivationLink($newUser));

                    // send an email with the activation link to the user
                    $m = new WireMail();
                    $m->to($newUser->email);
                    $m->from($this->input_email);
                    $this->setSenderName($m);
                    $m->subject($this->_('Action required to activate your account'));
                    $m->title($this->_('Please click the link to verify your registration'));
                    $m->bodyHTML($this->getLangValueOfConfigField('input_activationtext'));
                    $m->mailTemplate($this->input_emailTemplate);
                    if($m->send()){
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
            // or in other words: check if user has been registered twice and has not activated his first registration
            $email_field_name = $this->getAttribute('id').'-email';

            if(array_key_exists($email_field_name, $this->formErrors)){
                $email = $this->getValue($email_field_name);
                // run only if an email address was entered
            if ($this->getActivationCode($email)) {
                //if($this->sendReminderMail($this->wire('users')->get('email='.$email))){
                    // overwrite alert message
                    $this->getAlert()->setCSSClass('alert_warningClass')->setText($this->_('It seems that you have already registered before but have forgotten to activate your account. To activate your account, please follow the instructions inside the mail that have been sent to you.'));
                }
            }


        }
        // render the form on the frontend
        return parent::render();
    }
}
