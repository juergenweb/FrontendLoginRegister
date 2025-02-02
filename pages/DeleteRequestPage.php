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
    use FrontendForms\Form;
    use FrontendForms\Email;
    use FrontendForms\Button as Button;
    use FrontendForms\Password as Password;
    use ProcessWire\WireException;

    class DeleteRequestPage extends FrontendLoginRegisterPages
    {

        /**
         * @throws WireException
         * @throws Exception
         */
        public function __construct(string $id = 'deleteaccount-form')
        {

            parent::__construct($id);

            /**
             * if user is guest -> not allowed to be here -> let's redirect to the homepage
             * redirect takes only place if page is not public reachable
             * */
            if(!$this->loginregisterConfig['input_deleteProfile']){
                $this->redirectToHomepage();
            } else {
                if(!$this->loginregisterConfig['input_publicDeletion']){
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
            if($this->user->isLoggedin()){
                $this->disableCaptcha();
            }

            // show email field only if a user is not logged in and the deletion page is publicly reachable
            if($this->loginregisterConfig['input_publicDeletion'] && (!$this->user->isLoggedin())){
                // user email field
                $email = new Email('email');
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
            if($this->loginregisterConfig['input_publicDeletion'] && (!$this->user->isLoggedin())){
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
         * @throws WireException
         */
        public function __toString()
        {
            return $this->render();
        }

        /**
         * Render the form markup
         * @return string
         * @throws WireException
         * @throws WireException
         */
        public function render(): string
        {

            $content = '';
            if (!$this->getSubmitWithAjax()) {
                $content .= $this->prependBody();
            }
            if ($this->isValid()) {

                if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                    // get the ID of the user language as stored inside the db
                    $this->stored_user_lang = $this->getSavedUserLanguage($this->user);

                    // change user language to the stored user language placeholder in the stored user language
                    $this->user->setLanguage($this->stored_user_lang);
                }

                // add placeholders !!important!!
                $this->createGeneralPlaceholders();

                // generate a random code
                $deleteCode = $this->createQueryCode(); // create the deletion code

                // create placeholder
                $this->setMailPlaceholder('deleteaccountlink', $this->createCodeLink('fl_deleteaccountpage', $deleteCode));

                // send an email with the deletion link to the user in the stored user language, not the site language
                $m = $this->newMailInstance($this->loginregisterConfig['input_mailmodule']);
                if($this->user->isLoggedin()){
                    $userEmail = $this->user->email;
                } else {
                    $userEmail = $this->getValue('email');
                }

                $m->to($userEmail);
                $this->setSenderEmail($m);
                $this->setSenderName($m);
                $m->subject($this->_('Action required to delete your account'));
                $m->title($this->_('Please click the link inside the mail'));

                if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                    $text = $this->getLangValueOfConfigField('input_deleteaccounttext', $this->loginregisterConfig,
                        $this->stored_user_lang->id);
                } else {
                    $text = $this->loginregisterConfig['input_deleteaccounttext'];
                }
                $body = $text . $this->generateNoReplyText();

                // Add the HTML body property to the Mail object
                Form::setBody($m, $body, $this->loginregisterConfig['input_mailmodule']);

                $m->mailTemplate($this->loginregisterConfig['input_emailTemplate']);

                if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                    // set back the language to the site language
                    $this->user->setLanguage($this->site_language_id);
                }

                // save user data only if mail was sent successfully
                if ($m->send()) {
                    // grab the user and store the random string inside the recovery code input
                    $this->user->of(false);
                    $this->user->fl_deleteaccount = $deleteCode; // save the code in the db
                    $this->user->fl_deleteaccountdatetime = time(); // save the datetime string in the db
                    if (!$this->user->save()) {
                        // output warning message that a problem occurs during saving of user
                        $this->savingUserProblemAlert();
                    }
                    $this->user->of(true);
                } else {
                    // output an error message that the mail could not be sent
                    $this->generateEmailSentErrorAlert();
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
