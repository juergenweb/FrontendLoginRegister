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

    class DeleteAccountPage extends FrontendLoginRegisterPages
    {

        /**
         * Every form must have an ID, so let's add it via the constructor
         * @throws WireException
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
                $activation_code = $this->wire('input')->queryStringClean(['validNames' => ['deleteaccountcode']]);
                $this->wire('session')->set('deletion', $activation_code);

                // redirect to the login page
                $this->wire('session')->redirect($this->login_page->url);
            }

            if ($this->checkForUser('deleteaccountcode')) // check if user exists first
            {

                // afterwards check if the deletion link is not expired
                if (!$this->checkForDeletionLinkExpired()) {

                    // default settings
                    $this->setAttribute('action',
                        $this->wire('page')->url . '?recoverylogindatacode=' . $this->queryString);
                    $this->setMaxAttempts(3);
                    $this->setMinTime(2);
                    $this->setSuccessMsg($this->_('Your account has been successfully deleted.'));
                    $this->setAttribute('action', $this->wire('page')->url . '?deleteaccountcode=' . $this->queryString);
                    $this->setSubmitWithAjax($this->useAjax);

                    // password
                    $pass = new Password('pass');
                    $pass->setRule('checkPasswordOfUser', $this->user);
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
         */
        private function checkForDeletionLinkExpired(): bool
        {

            if ((time() - $this->user->getUnformatted('fl_deleteaccountdatetime')) > 300) {
                $this->getAlert()->setCSSClass('alert_dangerClass')->setText(sprintf($this->_('Your deletion link is expired. It was only valid for 5 minutes. Please %s for a new deletion link.'),
                    $this->___requestLink()->___render()));
                $this->showForm = false;

                //delete deletion code and time
                $this->user->of(false);
                $this->user->fl_deleteaccount = '';
                $this->user->fl_deleteaccountdatetime = '';
                $this->user->save();
                $this->user->of(true);
                return true;
            }
            return false;
        }

        /**
         * @return string
         * @throws WireException
         */
        public function __toString(): string
        {
            return $this->render();
        }

        /**
         * Render the form markup
         * @return string
         * @throws WireException
         */
        public function render(): string
        {
            if ($this->___isValid()) {
                $content = '';
                // delete the user
                if (!$this->wire('users')->delete($this->user)) {
                    // problem deleting the user - set an alert to inform the user
                    $this->getAlert()->setCSSClass('alert_dangerClass')->setText($this->_('Unfortunately there was a technical problem deleting your account. Please try it once more or contact the webmaster of the site.'));
                }
            } else {
                $content = $this->wire('page')->body;
            }
            // render the form on the frontend
            $content .= parent::render();
            return $content;
        }

    }
