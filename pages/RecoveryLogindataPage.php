<?php
    declare(strict_types=1);

    namespace FrontendLoginRegister;

    /*
     * Class for a form to change the password if you have forgotten it
     *
     * Created by Jürgen K.
     * https://github.com/juergenweb
     * File name: RecoveryLogindataPage.php
     * Created: 06.07.2022
     */

    use Exception;
    use FrontendForms\Button as Button;
    use FrontendForms\Password as Password;
    use FrontendForms\PasswordConfirmation as PasswordConfirmation;
    use FrontendForms\Username as Username;
    use ProcessWire\WireException;

    class RecoveryLogindataPage extends FrontendLoginRegisterPages
    {

        /**
         * Every form must have an ID, so let's add it via the constructor
         * @throws WireException
         * @throws Exception
         */
        public function __construct(string $id = 'recoverylogindata-form')
        {
            parent::__construct($id);

            // if a user is logged in -> no need to be here -> let's redirect to the homepage
            $this->redirectToHomepage();

            // check if recovery get parameter exists
            $this->checkForQueryString('recoverylogindatacode');

            // check if user with this recovery code exists in the database - sets the user property
            $this->checkForUser('recoverylogindatacode');

            // default settings
            $this->setMaxAttempts(5);
            $this->setMinTime(3); // 3 seconds
            $this->setMaxTime(600); // 10 minutes
            $this->setAttribute('action', $this->wire('page')->url . '?recoverylogindatacode=' . $this->queryString);
            $this->setSubmitWithAjax($this->useAjax);

            // create the success message and add username field depending on the login type (username/password or email/password)
            if ($this->loginregisterConfig['input_selectlogin'] == 'username') {
                $successMsg = $this->_('Your new login data have been saved. You can now log into your account.');

                // username (show only if username and password are selected as login data)
                $usernameText = '<p>' . $this->_('If you have forgotten your username too, you can enter a new one here. Otherwise, let this field empty and fill out only the password fields.') . '</p>';
                // sanitizers added: pageName, text
                // validation rules added: required, usernamesyntax, uniqueusername
                $username = new Username('username');
                $username->getFieldWrapper()->prepend($usernameText);
                $this->add($username);
            } else {
                $successMsg = $this->_('Your new password has been saved, and you can log in into your account now.');
            }
            $this->setSuccessMsg($successMsg . ' ' . $this->___loginLink()->___render());

            //pass
            // sanitizers added: text
            // validation rules added: meetsPasswordConditions, required, safePassword
            $pass = new Password('password');
            $pass->showPasswordToggle((bool)(!$this->loginregisterConfig['input_hide_passwordtoggle']));
            $pass->showPasswordRequirements(); // show the conditions for the password
            $this->add($pass);

            //password confirmation
            // sanitizers added: text
            // validation rules added: meetsPasswordConditions, required, equals
            $passwordConfirm = new PasswordConfirmation('password-confirm', $this->getID() . '-password');
            $passwordConfirm->showPasswordToggle()->setLabel($this->_('Show password confirmation'));
            $this->add($passwordConfirm);

            $button = new Button('submit');
            $buttonText = $this->_('Save new password');
            if ($this->loginregisterConfig['input_selectlogin'] == 'username') {
                $buttonText = $this->_('Save new login data');
            }
            $button->setAttribute('value', $buttonText);
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
         * Render the form
         * @return string
         * @throws WireException
         */
        public function render(): string
        {
            $content = '';
            if (!$this->getSubmitWithAjax()) {
                $content .= $this->prependBody();
            }
            if ($this->___isValid()) {
                // grab the user and store the new password
                $this->user->setOutputFormatting(false);
                $this->user->fl_recoverylogindata = ''; // delete the recovery code
                $this->user->fl_recoverylogindatadatetimeDateTime = ''; // delete the recovery code time stamp
                // save username only if the value is present
                if (($this->loginregisterConfig['input_selectlogin'] == 'username') && ($this->getValue('username'))) {
                    $this->user->name = $this->getValue('username');
                }
                $this->user->pass = $this->getValue('password');
                $this->user->save();
                $this->user->setOutputFormatting();
            } else {
                if ($this->getSubmitWithAjax()) {
                    $content .= $this->prependBody();
                }
            }

            // render the form on the frontend
            return $content . parent::render();
        }

    }
