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
use function ProcessWire\_n;

class RecoveryLogindataPage extends FrontendLoginRegisterPages
{

    use checkUser;

    /**
     * Every form must have an id, so let's add it via the constructor
     * @throws WireException
     * @throws Exception
     */
    public function __construct(string $id = 'recoverylogindata-form')
    {
        parent::__construct($id);

        // if user is logged in -> no need to be here -> lets redirect to the homepage
        $this->redirectToHomepage();

        // check if recovery get parameter exists
        $this->checkForQueryString('recoverylogindatacode');

        // check if user with this recovery code exists in the database
        $this->checkForUser('recoverylogindatacode');

        // default settings
        $this->setMaxAttempts(5);
        $this->setMinTime(3); // 3 seconds
        $this->setMaxTime(600); // 10 minutes
        $this->setAttribute('action', $this->wire('page')->url . '?recoverylogindatacode=' . $this->queryString);
        $quty = ($this->input_selectlogin == 'username') ? 2 : 1;
        $pwtext = $this->_('Your new password has been saved, and you can log in into your account now.');
        $usernametext = $this->_('Your new login data have been saved. You can now log into your account.');
        $successMsg = _n($pwtext, $usernametext, $quty);
        $this->setSuccessMsg($successMsg.' '.$this->___loginLink()->___render());

        // username (show only if username and password are selected as login data)
        if ($this->input_selectlogin == 'username') {
            $usernameText = '<p>' . $this->_('If you have forgotten your username too, you can enter a new one here. Otherwise, let this field empty and fill out only the password fields.') . '</p>';
            $username = new Username();
            $username->removeRule('required');
            $username->getFieldWrapper()->prepend($usernameText);
            $this->add($username);
        }

        //pass
        $pass = new Password('password');
        $pass->showPasswordToggle(); // add a checkbox below the input field to toggle show/hide the password in plain text
        $pass->showPasswordRequirements(); // show the conditions for the password
        $this->add($pass);

        //password confirmation
        $passwordConfirm = new PasswordConfirmation($this->getID() . '-password', 'password-confirm');
        $passwordConfirm->showPasswordToggle()->setLabel($this->_('Show password confirmation'));
        $this->add($passwordConfirm);

        $button = new Button('submit');
        $buttonText = ($this->input_selectlogin == 'username') ? $this->_('Save new login data') : $this->_('Save new password');
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

        if ($this->isValid()) {
            // grab the user and store the new password
            $this->user->setOutputFormatting(false);
            $this->user->fl_recoverylogindata = ''; // delete the recovery code
            $this->user->fl_recoverylogindatadatetimeDateTime = ''; // delete the recovery code time stamp
            // save username only if value is present
            if (($this->input_selectlogin == 'username') && ($this->getValue('username'))) {
                $this->user->name = $this->getValue('username');
            }
            $this->user->pass = $this->getValue('password');
            $this->user->save();
            $this->user->setOutputFormatting();
        }

        // render the form on the frontend
        $content =  $this->wire('page')->body;
        $content .= parent::render();
        return $content;
    }

}
