<?php
    declare(strict_types=1);

    namespace FrontendLoginRegister;

    /*
     * Class for activating a user via an activation code sent with a query string
     * If the user clicks on the link inside the activation email he will be redirected to this page,
     * where his account will be activated in the database
     *
     * Created by Jürgen K.
     * https://github.com/juergenweb
     * File name: activation.php
     * Created: 06.07.2022
     */

    use FrontendForms\Alert;
    use ProcessWire\WireException;

    class ActivationPage extends FrontendLoginRegisterPages
    {

        protected Alert $alert; // the alert object
        protected bool $verify = false; // verify the account (true) or delete the user (false)
        protected string $fl_activation = '';
        protected int $fl_activationdatetime = 0;

        public function __construct()
        {

            parent::__construct('activation');

            // if a user is logged in -> no need to be here -> let's redirect to the homepage
            $this->redirectToHomepage();

            // check for the presence of activation code or not registered code
            $activation_code = $this->checkForQueryString('activationcode', false);
            $not_registered_code = $this->checkForQueryString('notregisteredcode', false);

            // no query string present at all, so redirect to the homepage
            if ((!$not_registered_code) && (!$activation_code)) {
                $url = $this->wire('pages')->get('/')->url;
                $this->wire('session')->redirect($url);
            }

            if ($not_registered_code) {
                // delete the user from the database
                $this->queryString = $not_registered_code;
                $this->verify = false;
            } else {
                // verify the user account
                $this->queryString = $activation_code;
                $this->verify = true;
            }

            $this->alert = new Alert();

        }

        /**
         * Render the alert
         * @return string
         * @throws WireException
         * @throws WireException
         */
        public function render(): string
        {
            //check if user with this activation code exists in the database
            if ($this->checkQueryStringUser('activation')) {

                if ($this->verify) {
                    // verify the account
                    $this->user->setOutputFormatting(false);
                    $this->user->fl_activation = ''; // delete the activation code
                    $this->user->fl_activationdatetime = time(); // set the activation code time stamp
                    if ($this->user->save()) {
                        // output success message
                        $this->alert->setText(sprintf($this->_('Your account has been successfully activated. %s'),
                            $this->loginLink()->render()));
                        $this->alert->setCSSClass('alert_successClass');
                    } else {
                        // output warning message that a problem occurs during saving of user
                        $this->savingUserProblemAlert();
                    }
                    $this->user->setOutputFormatting();
                } else {
                    // delete the account
                    $this->user->delete();
                    // set the alert
                    $this->alert->setText(sprintf($this->_('Thank you for your feedback! The corresponding user account has been permanently deleted and you will not receive any further mails from %s.'),
                        $this->wire('pages')->get('/')->httpUrl));
                    $this->alert->setCSSClass('alert_successClass');
                }
            } else {
                $this->alert->setText($this->_('Sorry, but something went wrong. This activation code has already been used, is not valid anymore or was not found in the database.'));
                $this->alert->setCSSClass('alert_dangerClass');
            }

            return $this->alert->render();
        }

        /**
         * @throws WireException
         */
        public function __toString()
        {
            return $this->render();
        }

    }
