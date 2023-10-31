<?php
    declare(strict_types=1);

    namespace FrontendLoginRegister;

    /*
     * Page to unlock user account after account was locked due to multiple attempts to log in with the same email/username
     * and different password combinations
     * Account is locked inside the database with a code
     * User has to enter the password to unlock the account
     *
     * Created by Jürgen K.
     * https://github.com/juergenweb
     * File name: UnlockAccountPage.php
     * Created: 02.03.2023
     */

    use Exception;
    use FrontendForms\Button as Button;
    use FrontendForms\Password as Password;
    use ProcessWire\WireException;

    class UnlockAccountPage extends FrontendLoginRegisterPages
    {

        /**
         * Every form must have an ID, so let's add it via the constructor
         * @throws WireException
         * @throws Exception
         */
        public function __construct(string $id = 'deletion-form')
        {
            parent::__construct($id);

            // if a user is logged in -> no need to be here -> let's redirect to the homepage
            $this->redirectToHomepage();

            // check query string contains as get parameter und lock code
            $this->checkForQueryString('unlockaccountcode');

            // check if user with this lock code exists in the database
            $this->checkForUser('unlockaccountcode');

            // default settings
            $this->setMaxAttempts(5);
            $this->setMinTime(2); // 2 seconds
            $this->setMaxTime(600); // 10 minutes
            $this->setSubmitWithAjax($this->useAjax);
            $this->setAttribute('action', $this->wire('page')->url . '?unlockaccountcode=' . $this->queryString);
            $successMsg = $this->_('Your account is now unlocked.');
            $this->setSuccessMsg($successMsg . ' ' . $this->___loginLink()->___render());
            $this->setPrependBody(true); // this disables the display of the body text after successfull form submission

            //pass
            $pass = new Password('password');
            $pass->showPasswordToggle((bool)(!$this->loginregisterConfig['input_hide_passwordtoggle']));
            $pass->setRule('required');
            $pass->setRule('checkPasswordOfUser', $this->user);
            $this->add($pass);

            $button = new Button('submit');
            $buttonText = $this->_('Unlock your account');
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
            if (!$this->setSubmitWithAjax()) {
                $content .= $this->prependBody();
            }
            if ($this->___isValid()) {
                // grab the user and remove the lock code from the database
                $this->user->setOutputFormatting(false);
                $this->user->fl_unlockaccount = ''; // delete the lock code in the database
                $this->user->save();
                $this->user->setOutputFormatting();
            } else {
                if ($this->setSubmitWithAjax()) {
                    $content .= $this->prependBody();
                }
            }
            return $content . parent::render();
        }

    }
