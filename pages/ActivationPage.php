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
use ProcessWire\WirePermissionException;

class ActivationPage extends FrontendLoginRegisterPages
{
    protected Alert $alert; // the alert object
    protected bool $verify = false; // verify the account (true) or delete the user (false)

    // NOTE: $fl_activation and $fl_activationdatetime below are never read or written anywhere in
    // this class - the actual activation code/timestamp fields live on the User page object
    // ($this->user->fl_activation / $this->user->fl_activationdatetime, set in render() below),
    // not on these class properties. Left untouched here since removing them is a separate,
    // behavior-neutral cleanup decision - candidates for removal in a follow-up.
    protected string $fl_activation = '';
    protected int $fl_activationdatetime = 0;

    /**
     * Set up the activation page: redirect an already-logged-in visitor home, then read which of
     * the two possible query strings (activation code, or "I did not register this" code) is
     * present and set $verify accordingly
     * @throws WireException
     * @throws WirePermissionException
     */
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
     * @throws WirePermissionException
     */
    public function render(): string
    {
        // check if user with this activation code exists in the database
        if ($this->checkQueryStringUser('activation')) {
            if ($this->verify) {
                // verify the account
                $this->user->setOutputFormatting(false);
                $this->user->fl_activation = ''; // delete the activation code
                $this->user->fl_activationdatetime = time(); // set the activation code time stamp
                if ($this->user->save()) {
                    // output success message
                    $this->setAlert(
                        'alert_successClass',
                        sprintf(
                            $this->_('Your account has been successfully activated. %s'),
                            $this->loginLink()->render()
                        ),
                        $this->alert
                    );
                } else {
                    // output warning message that a problem occurs during saving of user
                    $this->savingUserProblemAlert();
                }
                $this->user->setOutputFormatting();
            } else {
                // delete the account
                if ($this->user->delete()) {
                    // set the success alert
                    $this->setAlert(
                        'alert_successClass',
                        sprintf(
                            $this->_('Thank you for your feedback! The corresponding user account has been permanently deleted and you will not receive any further mails from %s.'),
                            $this->wire('pages')->get('/')->httpUrl
                        ),
                        $this->alert
                    );
                } else {
                    // problem deleting the user - set an alert to inform the visitor, same wording as
                    // DeleteAccountPage::render() uses for the same failure
                    $this->setAlert(
                        'alert_dangerClass',
                        $this->_('Unfortunately there was a technical problem deleting your account. Please try it once more or contact the webmaster of the site.'),
                        $this->alert
                    );
                }
            }
        } else {
            $this->setAlert('alert_dangerClass', $this->_('Sorry, but something went wrong. This activation code has already been used, is not valid anymore or was not found in the database.'), $this->alert);
        }

        return $this->alert->render();
    }

    /**
     * Return the rendered alert markup
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
