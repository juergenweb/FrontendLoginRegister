<?php
declare(strict_types=1);

namespace FrontendLoginRegister;
// checked 27.3
/*
 * Class for requesting a deletion link for the user account
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: DeleteRequestPage.php
 * Created: 06.07.2022
 */

use FrontendForms\Button as Button;
use FrontendForms\Password as Password;
use ProcessWire\WireException;
use function ProcessWire\wireMail;

class DeleteRequestPage extends FrontendLoginRegisterPages
{

    /**
     * @throws WireException
     */
    public function __construct(string $id = 'deleteaccount-form')
    {

        parent::__construct($id);

        // if user is guest -> not allowed to be here -> lets redirect to the homepage
        $this->redirectToHomepage(true);

        // default settings
        $this->setMaxAttempts(5);
        $this->setMinTime(2);
        $this->setMaxTime(3600);
        $this->setSuccessMsg($this->_('A link to complete your account deletion has been sent to your email address.'));
        if(!$this->enable_captcha_loggedin) {
            $this->disableCaptcha(); // disable Captcha
        }

        // password field
        // sanitizers added: text
        // validation rules added: required, checkPasswordOfUser
        $pass = new Password('pass');
        $pass->setRule('checkPasswordOfUser', $this->user);
        // remove unnecessary validation rules
        $pass->removeRule('safePassword');
        $pass->removeRule('meetsPasswordConditions');
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
    public function render():string
    {

        if ($this->isValid()) {

            $content = '';

            // get the id of the user language as stored inside the db
            $this->stored_user_lang = $this->getSavedUserLanguage($this->user);

            // change user language to the stored user language placeholder in the stored user language
            $this->user->setLanguage($this->stored_user_lang);

            // add placeholders !!important!!
            $this->createGeneralPlaceholders();

            // generate a random code
            $deleteCode = $this->createQueryCode(); // create the deletion code

            // create placeholder
            $this->setMailPlaceholder('deleteaccountlink', $this->createCodeLink('fl_deleteaccountpage', $deleteCode));

            // send an email with the deletion link to the user in the stored user language, not the site language
            $m = wireMail();
            $m->to($this->user->email);
            $m->from($this->loginregisterConfig['input_email']);
            $this->setSenderName($m);
            $m->subject($this->_('Action required to delete your account'));
            $m->title($this->_('Please click the link inside the mail'));
            $m->bodyHTML($this->getLangValueOfConfigField('input_deleteaccounttext', $this->loginregisterConfig,
                $this->stored_user_lang->id).$this->___generateNoReplyText());
            $m->mailTemplate($this->loginregisterConfig['input_emailTemplate']);

            // set back the language to the site language
            $this->user->setLanguage($this->site_language_id);

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
            $content = $this->wire('page')->body;
        }
        // render the form on the frontend
        $content .= parent::render();
        return $content;
    }

}
