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
use ProcessWire\TfaEmail as TfaEmail;
use ProcessWire\WireException;

class ActivationPage extends FrontendLoginRegisterPages
{

    use checkUser;

    protected Alert $alert; // the alert object
    protected bool $verify = false; // verify the account (true) or delete the user (false)

    public function __construct()
    {

        parent::__construct('activation');

        // if user is logged in -> no need to be here -> lets redirect to the homepage
        $this->redirectToHomepage();

        // check for presence of activation code or not registered code
        $activation_code = $this->checkForQueryString('activationcode', false);
        $not_registered_code = $this->checkForQueryString('notregisteredcode', false);

        if ($not_registered_code != '') {
            // delete the user from the database
            $this->queryString = $not_registered_code;
            $this->verify = false;
        } else {
            if ($activation_code != '') {
                // verify the user account
                $this->queryString = $activation_code;
                $this->verify = true;
            } else {
                // no query string present, so redirect to the homepage
                $url = $this->wire('pages')->get('/')->url;
                $this->wire('session')->redirect($url);
            }
        }

        $this->alert = new Alert();

    }

    /**
     * Render the alert
     * @return string
     * @throws WireException
     * @throws WireException
     */
    public function render():string
    {

        //check if user with this activation code exists in the database
        if ($this->checkQueryStringUser('activation')) {

            if ($this->verify) {
                // verify the account
                $this->user->setOutputFormatting(false);
                $this->user->fl_activation = ''; // delete the activation code
                $this->user->fl_activationdatetime = time(); // set the activation code time stamp

                // check if TfaEmail is installed and enable it for the user automatically
                if ($this->wire('modules')->isInstalled('TfaEmail')) {
                    $tfa = new TfaEmail();
                    if ($tfa->autoEnableSupported($this->user)) {
                        $tfa->autoEnableUser($this->user);
                    }
                }
                if ($this->user->save()) {
                    // output success message
                    $this->alert->setText(sprintf($this->_('Your account has been successfully activated. %s'),
                        $this->___loginLink()->___render()));
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

        return $this->alert->___render();
    }

    /**
     * @throws WireException
     */
    public function __toString()
    {
        return $this->render();
    }

}
