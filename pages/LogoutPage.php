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

class LogoutPage extends FrontendLoginRegisterPages
{

    protected Alert $alert;

    public function __construct()
    {
        // if user is guest -> redirect to homepage
        $this->redirectToHomepage(true);

        $this->wire('session')->logout();
        $this->alert = new Alert();
        $this->alert->setCSSClass('alert_successClass');
        $this->alert->setText($this->_('You have been successfully logged out.'));

    }

    /**
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
        return $this->alert->___render();
    }

}
