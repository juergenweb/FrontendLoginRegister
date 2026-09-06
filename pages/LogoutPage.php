<?php

declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Class for logging out a user if he is logged in
 * Returns an alert message that the user has been logged out successfully
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: LogoutPage.php
 * Created: 06.07.2022
 */

use FrontendForms\Alert as Alert;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

class LogoutPage extends FrontendLoginRegisterPages
{
    protected Alert $alert; // the alert object

    /**
     * Set up the logout page: redirect a guest visitor to the homepage (nothing to log out),
     * otherwise log the current visitor out and prepare the success alert
     * @throws WireException
     * @throws WirePermissionException
     */
    public function __construct()
    {
        parent::__construct('logout');

        // if user is guest -> redirect to homepage
        $this->redirectToHomepage(true);

        $this->wire('session')->logout();
        $this->alert = new Alert();
        $this->setAlert('alert_successClass', $this->_('You have been successfully logged out.'), $this->alert);
    }

    /**
     * Return the rendered alert markup
     * @return string
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Render the alert
     * @return string
     */
    public function render(): string
    {
        return $this->alert->render();
    }
}
