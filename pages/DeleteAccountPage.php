<?php

declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Class for deleting a user via a deletion link and his password
 * Shows a form for entering the password. Afterwards the user will be deleted
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: DeleteAccountPage.php
 * Created: 06.07.2022
 */

use Exception;
use FrontendForms\Alert;
use FrontendForms\Button as Button;
use FrontendForms\InputCheckbox;
use FrontendForms\Link;
use FrontendForms\Password as Password;
use ProcessWire\WireException;
use ProcessWire\WireLog;
use ProcessWire\WirePermissionException;

class DeleteAccountPage extends FrontendLoginRegisterPages
{
    // NOTE: $fl_deleteaccount and $fl_deleteaccountdatetime below are never read or written
    // anywhere in this class - the actual deletion code/timestamp fields live on the User page
    // object ($this->user->fl_deleteaccount / $this->user->fl_deleteaccountdatetime, used in
    // checkForDeletionLinkExpired() below), not on these class properties. Left untouched here
    // since removing them is a separate, behavior-neutral cleanup decision - candidates for
    // removal in a follow-up.
    protected string $fl_deleteaccount = '';
    protected int|string $fl_deleteaccountdatetime = '';

    /**
     * Every form must have an ID, so let's add it via the constructor
     * @param string $id
     * @throws WireException
     * @throws WirePermissionException
     * @throws Exception
     */
    public function __construct(string $id = 'deletion-form')
    {
        parent::__construct($id);

        // check if a querystring is present in the get parameter - Otherwise redirect to the homepage
        $this->checkForQueryString('deleteaccountcode');

        // user has to be logged in to be able to delete his account on this page
        if ($this->wire('user')->isGuest()) {
            // create a session for usage in login page and add query string as session value
            $deletion_code = $this->wire('input')->queryStringClean(['validNames' => ['deleteaccountcode']]);
            $this->wire('session')->set('deletion', $deletion_code);

            // redirect to the login page
            $this->wire('session')->redirect($this->login_page->url);
        }

        $this->wire('session')->remove('deletion');

        // check if user exists first
        if ($this->checkForUser('deleteaccountcode')) {
            // The code alone identifies which account it belongs to, but not that the person
            // currently logged in IS that account - without this check, any logged-in visitor who
            // gets hold of someone else's deletion code (and that account's password) could delete
            // that other account instead of their own. Treat a mismatch the same way checkForUser()
            // itself treats "no user found" - same alert, same hidden form - so this doesn't reveal
            // anything about whether the code is valid for some other account.
            if ($this->user->id !== $this->wire('user')->id) {
                $this->setAlert('alert_dangerClass', $this->_('Sorry, but no user was found with this code.'));
                $this->showForm = false;
                return;
            }
            // afterwards check if the deletion link is not expired
            if (!$this->checkForDeletionLinkExpired()) {
                // default settings
                $this->setMaxAttempts(3);
                $this->setMinTime(2);
                $this->setSuccessMsg($this->_('Your account has been successfully deleted.'));
                $this->setAttribute('action', $this->wire('page')->url . '?deleteaccountcode=' . $this->queryString);
                $this->setSubmitWithAjax($this->useAjax);
                $this->disableCaptcha();

                // password
                $pass = new Password('pass');
                $pass->setRule('checkPasswordOfUser', $this->user);
                // no need to display the password requirements here
                $pass->showPasswordRequirements(false);
                $pass->showPasswordToggle(!$this->loginregisterConfig['input_hide_passwordtoggle']);
                $this->add($pass);

                // create info box for account deletion
                $info = new Alert();
                $info->setAttribute('id', 'deletion-hint');
                $info->setCSSClass('alert_warningClass');
                $text = '<p>' . $this->_('Are you sure that you want to delete your whole account?') . '<br>';
                $text .= $this->_('Your profile and related account information will be deleted forever!') . '<br>';
                $text .= $this->_('Please note that there is no option to restore the account once it is deleted.') . '<br>';
                $text .= $this->_('If you are absolutely sure, please tick the box below to confirm your deletion.') . '</p>';
                $info->setText($text);
                $this->add($info);

                // confirmation checkbox
                $confirm = new InputCheckbox('confirm-deletion');
                $confirm->setLabel($this->_('I have read the notes above and confirm to delete my account.'));
                $confirm->setRule('required')->setCustomMessage($this->_('You have to confirm the deletion of your account.'));
                $this->add($confirm);

                // button
                $button = new Button('submit');
                $button->setAttribute('value', $this->_('Delete account'));
                $this->add($button);
            }
        }
    }

    /**
     * Method to render a link to request a new deletion link if it has been expired
     * @return Link
     */
    public function ___requestLink(): Link
    {
        $link = new Link('request-link');
        $link->setPageLink($this->delete_request_page);
        $link->setLinkText($this->_('request'));
        return $link;
    }

    /**
     * Check if the deletion link is expired or not
     * @return bool - returns true if the link is expired
     * @throws WireException
     * @throws WirePermissionException
     */
    private function checkForDeletionLinkExpired(): bool
    {
        if ((time() - $this->user->getUnformatted('fl_deleteaccountdatetime')) > 300) {
            $this->setAlert('alert_dangerClass', sprintf($this->_('Your deletion link is expired. It was only valid for 5 minutes. Please %s for a new deletion link.'),
                $this->requestLink()->render()));
            $this->showForm = false;

            // delete deletion code and time
            $this->user->of(false);
            $this->user->fl_deleteaccount = '';
            $this->user->fl_deleteaccountdatetime = '';
            if (!$this->user->save()) {
                // the expired code could not be cleared - not user-facing (the "link expired"
                // alert above stays correct either way), but worth a log entry so a stale code
                // sitting in the database does not go unnoticed
                $log = new WireLog();
                $log->save('deletion-code-cleanup', 'Could not clear expired deletion code for user ID ' . $this->user->id);
            }
            $this->user->of(true);
            return true;
        }
        return false;
    }

    /**
     * Return the rendered form markup
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Render the form markup
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    public function render(): string
    {
        $content = '';
        if (!$this->getSubmitWithAjax()) {
            $content .= $this->prependBody();
        }
        if ($this->isValid()) {
            // delete the user
            if (!$this->wire('users')->delete($this->user)) {
                // problem deleting the user - set an alert to inform the user
                $this->setAlert('alert_dangerClass', $this->_('Unfortunately there was a technical problem deleting your account. Please try it once more or contact the webmaster of the site.'));
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
