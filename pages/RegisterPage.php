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

    use Exception;
    use FrontendForms\Button as Button;
    use FrontendForms\Form;
    use FrontendForms\Privacy as Privacy;
    use FrontendForms\PrivacyText as PrivacyText;
    use ProcessWire\TfaEmail;
    use ProcessWire\User;
    use ProcessWire\WireException;
    use ProcessWire\WirePermissionException;

    class RegisterPage extends FrontendLoginRegisterPages
    {
        protected Privacy $privacy; // the privacy field object with the checkbox
        protected PrivacyText $privacyText; // the privacy hint text only

        /**
         * @throws WireException
         * @throws Exception
         */
        public function __construct(string $id = 'register-form')
        {

            parent::__construct($id);

            // if a user is logged in -> no need to be here -> let's redirect to the homepage
            $this->redirectToHomepage();

            // set default form values
            $this->setMaxAttempts(5);
            $this->setMinTime(3);
            $this->setMaxTime(3600);
            $this->setSubmitWithAjax($this->useAjax);

            // create privacy objects and add them to the form object
            $this->privacy = new Privacy('privacy');
            $this->add($this->privacy);
            $this->privacyText = new PrivacyText('privacy-text');
            $this->add($this->privacyText);

            // add the various form fields dynamically depending on the settings in the backend
            $this->createFormFields('input_registration', $this);

            // show or hide the display of the toggle checkbox next to password fields
            $this->getFormelementByName('pass')->showPasswordToggle(!$this->loginregisterConfig['input_hide_passwordtoggle']);
            $this->getFormelementByName('pass-confirm')->showPasswordToggle(!$this->loginregisterConfig['input_hide_passwordtoggle']);

            // add the button
            $button = new Button('submit');
            $button->setAttribute('value', $this->_('Register'));
            $this->add($button);

            $this->setUploadPath($this->tmp_profile_image_dir_path);

        }

        /**
         * @return string
         * @throws \DOMException
         * @throws \ProcessWire\WireException
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
        protected function getUserByActivationCode(string $email): User|null
        {
            $user = $this->wire('users')->get('email=' . $email);
            // check if the activation code exists
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
        protected function getDeleteDateTime(bool $timestamp = false): string
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
         * @throws \DOMException
         */
        public function render(): string
        {
            $content = '';
            if (!$this->getSubmitWithAjax()) {
                $content .= $this->prependBody();
            }
            // add privacy notice if set
            $privacyType = 1;
            if (array_key_exists('input_privacy', $this->loginregisterConfig)) {
                $privacyType = (int)$this->loginregisterConfig['input_privacy'];
            }

            // create and add the privacy notice type
            switch ($privacyType) {
                case(1): // checkbox has been selected
                    // remove PrivacyText element
                    $this->remove($this->privacyText);
                    break;
                case(2): // text only has been selected
                    // remove Privacy element
                    $this->remove($this->privacy);
                    break;
                default: // show none of them has been selected
                    // remove both
                    $this->remove($this->privacyText);
                    $this->remove($this->privacy);
            }

            if ($this->isValid()) {

                $activationCode = $this->createQueryCode();// create the activation code

                // create a new user
                $newUser = new User();
                $newUser->of(false);

                // set page language as default user language if language support is enabled
                if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                    $newUser->setLanguage($this->user->language);
                }

                // set values for all fields inside the form
                $this->setFormFieldValues($newUser);

                // save the activation code
                $newUser->fl_activation = $activationCode;

                // add all roles depending on configuration settings to the new user
                foreach ($this->loginregisterConfig['input_roles'] as $role) {
                    $newUser->addRole($role);
                }

                if ($newUser->save()) {

                    // save uploaded profile image
                    $this->saveProfileImage($newUser, 'register-form');

                    if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                        // get the ID of the user language as stored inside the db
                        $this->stored_user_lang = $this->getSavedUserLanguage($newUser);

                        // change user language to the stored user language placeholder in the stored user language
                        $this->user->setLanguage($this->stored_user_lang);
                    }

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
                    $m = $this->newMailInstance($this->loginregisterConfig['input_mailmodule']);
                    $m->to($newUser->email);
                    $this->setSenderEmail($m);
                    $this->setSenderName($m);
                    $m->subject($this->_('Action required to activate your account'));
                    $m->title($this->_('Please click the link to verify your registration'));

                    if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                        $text = $this->getLangValueOfConfigField('input_activationtext', $this->loginregisterConfig,
                            $this->stored_user_lang->id);
                    } else {
                        $text = $this->loginregisterConfig['input_activationtext'];
                    }
                    $body = $text . $this->generateNoReplyText();

                    // Add the HTML body property to the Mail object
                    Form::setBody($m, $body, $this->loginregisterConfig['input_mailmodule']);

                    $m->mailTemplate($this->loginregisterConfig['input_emailTemplate']);

                    $activation_mail_sent = $m->send();

                    if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                        // set back the language to the site language
                        $this->user->setLanguage($this->site_language_id);
                    }

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
                // the form is not valid, so check if email address exists but is not verified
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
                if ($this->getSubmitWithAjax()) {
                    $content .= $this->prependBody();
                }
            }
            // render the form on the frontend
            return $content . parent::render();
        }
    }
