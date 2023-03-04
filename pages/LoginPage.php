<?php
declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Class to create a login form
 * Supports TfaEmail too
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: LoginPage.php
 * Created: 06.07.2022
 */

use Exception;
use FrontendForms\Button as Button;
use FrontendForms\Email as Email;
use FrontendForms\Form as Form;
use FrontendForms\InputText as InputText;
use FrontendForms\Link as Link;
use FrontendForms\Password as Password;
use FrontendForms\Username as Username;
use ProcessWire\HookEvent as HookEvent;
use ProcessWire\Tfa as Tfa;
use ProcessWire\User;
use ProcessWire\Wire as Wire;
use ProcessWire\WireException;
use ProcessWire\WireLog as WireLog;
use ProcessWire\WirePermissionException;
use function ProcessWire\wireMail;

class LoginPage extends FrontendLoginRegisterPages
{
    use activation;

    protected Tfa $tfa; // Tfa object
    protected Link $cl; // cancel link object
    protected Link $rl; // register link object
    protected Link $pfl; // password forgotten link object
    protected string $loginCode = ''; // the login code which will be sent via email
    // after how many failed attempts with same username/email and different passwords combinations
    // should the user account locked via code inside the database
    protected int|string $lock_number = 5; // number after how many attempts a user account should be locked if maxAttempts is not set

    /**
     * Every form must have an id, so let's add it via the constructor
     * @throws WireException
     */
    public function __construct(string $id = 'login-form')
    {
        parent::__construct($id);

        $this->redirectToHomepage(); // if user is logged in -> no need to be here -> lets redirect to the homepage

        $this->tfa = new Tfa(); // instantiate a new instance of Tfa (2-factor authentication)
        $this->cl = new Link('cancelLink'); // instantiate a new instance for a link
        $this->rl = new Link('registerLink'); // instantiate a new instance for a link
        $this->pfl = new Link('passwordforgottenLink'); // instantiate a new instance for a link

        // Hook to replace start method entirely
        $this->addHookBefore('Tfa::start', $this, 'start');

        // Grab the login code and add it to the property "logincode"
        $this->addHookBefore('TfaEmail::emailCode', $this, 'getCode');

        // Hook to return an error message if mail with authentication could not be sent
        $this->addHookAfter('TfaEmail::emailCode', $this, 'checkCodeSent');

    }

    /**
     * Method to create a link to cancel the login process
     * @return Link
     * @throws WireException
     */
    public function ___cancelLink():Link
    {
        $this->cl->setPageLink($this->wire('page'));
        $this->cl->setQueryString('cancel=1');
        $this->cl->setLinkText($this->_('Cancel'));
        $this->cl->wrap();
        return $this->cl;
    }

    /**
     * Method to create a link for forgotten your password
     * @return Link
     * @throws WireException
     */
    public function ___passwordForgottenLink():Link
    {
        $this->pfl->setPageLink($this->wire('pages')->get('template=fl_forgotlogindatapage'));
        if ($this->input_selectlogin === 'username') {
            $this->pfl->setLinkText($this->_('Forgot login data?'));
        } else {
            $this->pfl->setLinkText($this->_('Forgot password?'));
        }
        $this->pfl->wrap();
        return $this->pfl;
    }

    /**
     * Method to create a link to cancel the login process
     * @return Link
     * @throws WireException
     */
    public function ___registerLink():Link
    {
        $this->rl->setPageLink($this->wire('pages')->get('template=fl_registerpage'));
        $this->rl->setLinkText($this->_('No Account? Register now!'));
        $this->rl->wrap();
        return $this->rl;
    }

    /**
     * Send an email with the unlock account link to the user
     * @param User $user
     * @param string $code
     * @return bool
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function sendAccountLockedMail(User $user, string $code):bool
    {
        // create placeholder for the email body
        $this->setMailPlaceholder('unlockaccountlink', $this->createCodeLink('fl_unlockaccountpage', $code));
        // set text for the placeholder login type
        if ($this->input_selectlogin == 'email') {
            $type = $this->_('email');
        } else {
            $type = $this->_('username');
        }
        $this->setMailPlaceholder('logintype', $type);

        $m = wireMail();
        $m->to($user->email);
        $m->from($this->input_email);
        $this->setSenderName($m);
        $m->subject($this->_('We have detected suspicious activity on your user account'));
        $m->title($this->_('Action required to unlock your account'));
        $m->bodyHTML($this->getLangValueOfConfigField('input_unlock_account'));
        $m->mailTemplate($this->input_emailTemplate);

        if ($m->send()) {
            return true;
        } else {
            // output an error message that the mail could not be sent
            $this->generateEmailSentErrorAlert();
            return false;
        }
    }

    /**
     * @return string
     * @throws WireException
     */
    public function __toString():string
    {
        return $this->render();
    }

    /**
     * Render the form
     * Renders 2 forms - one for password and email/username and another for entering the authentication code if enabled
     * @return string
     * @throws WireException
     * @throws Exception
     */
    public function render():string
    {

        /*
         Check if "deletion" session from deletion link is active
         This is the case if user clicks on the deletion link inside the email and is not logged in
         */
        if ($this->wire('session')->get('deletion')) {
            // user comes from redirect of delete account page, but was not logged in
            $alert = $this->getAlert();
            $alert->setText($this->_('You have to log in to delete your account.'));
            $alert->setCSSClass('alert_warningClass');
        }

        if ($this->tfa->active()) {
            $tfaSession = $this->wire('session')->get('tfa'); // grab the tfa session
            $module = $this->tfa->getModule(); // get the TFA module object which is used by the user who currently tries to log in
            $user = $this->wire('users')->get($tfaSession['id']);// grab the user by session id

            /*
             1) check if session key is valid -
             This code is copied from the process() method inside the Tfa.php
             */
            $key = $this->wire('sanitizer')->text($this->wire('input')->get('tfa'));
            // if invalid key, abort and go back to first page
            if (empty($key) || $key !== $this->tfa->getSessionKey() || $this->tfa->getUser()->isLoggedin()) {
                $this->wire('session')->set('error',
                    json_encode(['alert_dangerClass' => $this->_('Your session key is not valid.')]));
                $this->tfa->sessionReset($this->tfa->startUrl);
            }

            /*
             2) check if time for authentication code is expired -
             This piece of code is copied from the process() method inside the Tfa.php
             */
            $initTime = $this->wire('session')->get('tfa')['time'];
            if (!$initTime || (time() - $initTime > $module->codeExpire)) {
                // more than the time set has passed since authentication, so make them start over
                $this->wire('session')->set('error',
                    json_encode(['alert_warningClass' => $this->_('Your code has expired. Please enter your data once more to get a new code.')]));
                $this->tfa->sessionReset($this->tfa->startUrl);
            }

            // Create the form for the authentication code
            $form = new Form('tfa');
            $this->form = $form;
            $form->setMaxAttempts(3);
            $form->setMinTime(3);
            $form->setMaxTime(3600);
            $form->setSuccessMsg($this->_('You are now logged in.'));
            $form->setAttribute('action', $this->wire('page')->url . '?tfa=' . $this->tfa->getSessionKey(true));
            $form->disableCaptcha(); // disable Captcha by default - no need for it

            // authentication code input field
            $tfaCode = new InputText('tfa_code');
            $tfaCode->setLabel($this->tfa->inputLabel);
            $tfaCode->setSanitizer('trim');
            $tfaCode->setRule('required');
            $tfaCode->setRule('checkTfaCode', $user,
                $module)->setCustomMessage($this->_('The authentication code is not correct.'));
            $form->add($tfaCode);

            // submit button
            $button = new Button('tfa_submit');
            $button->setAttribute('value', $this->_('Send'));
            $form->add($button);
            // show or hide the cancel link depending on settings
            if ($this->tfa->showCancel) {
                $form->add($this->___cancelLink());
            }

            $this->addLogFailedLoginAttempt();
            if ($form->isValid()) {
                $this->tfa->sessionReset(); // remove all tfa session values
                $this->wire('session')->forceLogin($user); // force login
                $this->redirectAfterLogin(); // redirect after login
            }

            $msg = $form->getAlert()->getText() ? $form->getAlert()->getText() : ''; // grab alert text if present

            // Only if TfaEmail module is used
            if (($module->className() == 'TfaEmail') && ($this->wire('session')->get('type'))) {
                // only on redirect from step 1 to 2
                $msg .= $this->_('A code has been sent to you by email - please enter it in the input field below.');
                $form->getAlert()->setCSSClass('alert_successClass');
                $this->wire('session')->remove('type');
            }

            // add info about how long the code will be valid
            $expireTime = $tfaSession['codes'][0]['expires'] - time();
            if (($expireTime) && $this->wire('user')->isGuest()) {
                // show info only if it will be submitted in time and user is not logged in
                $msg .= '<br>' . sprintf($this->_('This code is still valid for %s seconds.'),
                        '<span id="expirationcounter">' . $expireTime . '</span>');
            }
            $form->getAlert()->setText($msg);
            return $form->render();
        } else {
            // render login form
            // TODO setMaxAttempts wieder auf z.b 5 ändern
            $this->setMaxAttempts(0);
            $this->setMinTime(3);
            $this->setMaxTime(3600);
            $this->setSuccessMsg($this->_('You are now logged in.'));

            if ($this->input_selectlogin === 'email') {
                // add the email field
                $email = new Email('email');
                $this->add($email);
            } else {
                // username
                $username = new Username('username');
                $this->add($username);
            }

            // pass
            $password = new Password('password');
            if ($this->input_selectlogin === 'email') {
                $password->setRule('matchEmail', 'email');
            } else {
                $password->setRule('matchUsername', 'username');
            }
            $password->showPasswordToggle();
            $this->add($password);

            // button
            $button = new Button('submit');
            $button->setAttribute('value', $this->_('Login'));
            $this->add($button);

            // password forgotten link
            $this->add($this->passwordForgottenLink());

            // register link
            if ($this->input_selectloginregister == 'loginregister') {
                $this->add($this->___registerLink());
            }

            $this->addLogFailedLoginAttempt();

            if ($this->isValid()) {

                $fieldname = ($this->input_selectlogin == 'username') ? 'name' : $this->input_selectlogin;

                if ($fieldname != 'name') {
                    // login with email
                    $user = $this->wire('users')->get($fieldname . '=' . $this->getValue($this->input_selectlogin));
                } else {
                    // login with username
                    $user = $this->wire('users')->get('name=' . $this->wire('input')->post('login-form-username'));
                }

                if ($this->checkIfAccountLocked($user)) {
                    // user account is locked - send mail with unlock code once more and change alert text
                    if ($this->sendAccountLockedMail($user, $user->fl_unlockaccount)) {
                        $this->getAlert()->removeCSSClass('alert_successClass')->setCSSClass('alert_dangerClass')->setText($this->_('Dear user! Your account is still blocked! An email with a link to unlock your account has been sent once more to your email address. Please follow the instructions inside the email to unlock your account again.'));
                    }
                } else {
                    //an activation code is present -> user has not activated his account till now
                    if ($user->fl_activation) {
                        // send reminder email to the user that he must activate his account
                        if ($this->sendReminderMail($user)) {
                            $this->getAlert()->setCSSClass('alert_warningClass')->setText($this->_('You have not verified your account yet. To activate your account, please follow the instructions inside the mail we have sent to you. Afterwards, you will be able to log in to the site.'));
                        }
                    } else {

                        // check if TFA is enabled in the settings
                        if ($this->input_tfa) {
                            // Hook to customize the email content of the authentication code mail
                            $this->addHookBefore('WireMail::send', $this, 'changeMailData');
                            $this->wire('session')->set('type',
                                'TfaEmail'); // set session for outputting message that a code was sent by email
                            $this->tfa->start($user->name, $this->getValue('pass')); // redirects
                        }
                        // try to log in the user
                        if ($this->wire('session')->login($user->name, $this->getValue('password'))) {
                            // login if tfa is not enabled
                            $this->redirectAfterLogin();// redirect after login
                        } else {
                            // for the rare use case that something went wrong during the session login
                            $alert = $this->getAlert();
                            $alert->setCSSClass('alert_dangerClass');
                            $alert->removeCSSClass('alert_successClass');
                            $alert->setText($this->_('We are sorry, but an unexpected error occurred. Please try it once more and if the problem persists, please inform the webmaster of this site.'));
                        }
                    }
                }
            } else {
                // grab the post values

                // grab the first field name: could be username or email
                $user_field_name = $this->getAttribute('id') . '-' . $this->input_selectlogin;

                if ($this->wire('session')->get($user_field_name)) {
                    $keys = $this->array_keys_multi($this->wire('session')->get($user_field_name));

                    if ($this->getMaxAttempts()) {
                        // set lock if $session blocked is set
                        $limit_reached = (!is_null($this->wire('session')->get('blocked')));
                    } else {
                        // set lock after fe 5 unsuccessful attempts matching username/email and password
                        $limit_reached = (count($keys) >= $this->lock_number);
                    }
                    if ($limit_reached) {

                        // check if always the same username or email was used
                        if (array_unique($keys)) {
                            // always the same email or username was used for the login attempts
                            // so check if a user exists with this username or email in the database
                            if ($this->wire('users')->get($this->input_selectlogin . '=' . $keys[0])) {
                                // user was found
                                $this->user = $this->wire('users')->get($this->input_selectlogin . '=' . $keys[0]);

                                // for the rare case that the form will be submitted again to prevent creation of new code
                                // do not create and send a code once more
                                $lock_account = (!$this->user->fl_unlockaccount);
                            }
                        }

                        // run code only if $lock_account is tre
                        if ($lock_account) {

                            // generate a random code
                            $lockCode = $this->createQueryCode(); // create the lock code

                            // send the email with the code to unlock the account and store the code inside the db
                            if ($this->sendAccountLockedMail($this->user, $lockCode)) {
                                // remove session blocked if present because the account has been locked
                                $this->wire('session')->remove('blocked');

                                // save the lock code inside the database
                                $this->user->setOutputFormatting(false);
                                $this->user->fl_unlockaccount = $lockCode; // code
                                $this->user->save();
                                $this->user->setOutputFormatting();

                                // output alert message that the account is locked now
                                $alert = $this->getAlert();
                                $alert->setCSSClass('alert_dangerClass');
                                $alert->removeCSSClass('alert_successClass');
                                $alert_text = $this->_('Unfortunately, too many unsuccessful login attempts were made, so this user account has now been blocked for security reasons.');
                                $alert_text .= '<br>' . $this->_('An email with a link to unlock your account has been sent to your email address.');
                                $alert_text .= '<br>' . $this->_('Please follow the instructions inside the email to unlock your account.');
                                $alert->setText($alert_text);

                                // do not display the form after account is locked
                                $this->showForm = false;
                            }
                        }
                    }
                }
            }

            // create error messages from session if present
            if ($this->wire('session')->get('error')) {
                $errorMsg = json_decode($this->wire('session')->get('error'), true);
                // get the first key in error array
                reset($errorMsg);
                $key = key($errorMsg);
                $this->getAlert()->setText($errorMsg[$key]);
                $this->getAlert()->setCSSClass($key);
                $this->wire('session')->remove('error');
            }

            return parent::render();
        }
    }

    /**
     * Get all username/email inputs of the unsuccessful login attempts
     * @param array $array - the session array
     * @return array
     */
    protected function array_keys_multi(array $array):array
    {
        $keys = array();

        foreach ($array as $key => $value) {
            $keys[] = $key;

            if (is_array($value)) {
                $keys = array_merge($keys, $this->array_keys_multi($value));
            }
        }

        // filter out all numeric values
        $keys = array_filter($keys, function ($item) {
            // return animal whose name is more than 3 characters
            if (!is_int($item)) {
                return $item;
            }
        });
        return array_values($keys);
    }

    /**
     * Write a log if failed login attempts have reached the max number of attempts
     * @return void
     * @throws WireException
     */
    protected function addLogFailedLoginAttempt():void
    {
        // check if logging is enabled and max attempts is set
        if (($this->frontendForms->input_logFailedAttempts) && ($this->getMaxAttempts())) {
            // check if the max number of attempts is reached
            if (!$this->checkMaxAttempts()) {
                //write the log file
                $log = new WireLog();
                $ip_address = $this->wire('session')->getIP();
                $log->save('failed-logins', 'IP: ' . $ip_address);
            }
        }
    }

    /**
     * Redirect after successful login to a pre-defined page from the settings
     * @return void
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function redirectAfterLogin():void
    {

        $query_string = '';
        // check if 'deletion' session is present
        if ($this->wire('session')->get('deletion')) {
            $query_string = '?' . $this->wire('session')->get('deletion');
            $id = $this->delete_page->id;
        } else {
            // get redirect page from module configuration settings
            $current_page_id = $this->wire('pages')->get('template=fl_loginpage')->id;

            switch (true) {
                case($this->input_redirectSuccess == -1):
                    if ($this->wire('session')->get('prevPage') > 0) {
                        $id = (int)$this->wire('session')->get('prevPage');
                        $this->wire('session')->remove('prevPage');
                    } else {
                        $id = $current_page_id;
                    }
                    break;
                case($this->input_redirectSuccess > 0):
                    $id = (int)$this->input_redirectSuccess;
                    break;
                default:
                    $id = $current_page_id;
            }
        }

        $this->wire('session')->set('loginID', $id);
        if (($this->wire('languages')) && (count($this->wire('languages')) > 1)) {
            $this->wire('session')->redirect($this->wire('pages')->get($id)->localUrl($this->wire('user')->language) . $query_string);
        } else {
            $this->wire('session')->redirect($this->wire('pages')->get($id)->url . $query_string);
        }
    }

    /**
     * This method replaces the start method from the Tfa class completely,
     * because we do not need to validate user and pass once more
     * The validation of user entered data was done before during the form validation
     * @param HookEvent $event
     * @return bool|void
     * @throws WireException
     * @throws WirePermissionException
     */
    public function start(HookEvent $event)
    {
        $event->replace = true;// replace the original start method completely
        // change the field "username" to "name" because this is the name in the DB
        $dbFieldName = ($this->input_selectlogin == 'username') ? 'name' : $this->input_selectlogin;
        // grab the user
        if ($dbFieldName != 'name') {
            // get user by email
            $user = $this->wire('users')->get($dbFieldName . '=' . $this->getValue($dbFieldName));
        } else {
            // get user by username
            $user = $this->wire('users')->get('name=' . $this->wire('input')->post('login-form-username'));
        }

        // check if user is not allowed to log in
        if (!$this->wire('session')->allowLogin($user->name, $user)) {
            $this->wire('session')->set('error',
                json_encode(['alert_dangerClass' => $this->_('Sorry, but you are not allowed to log in.')]));
        }

        // remove all session variables
        $this->tfa->sessionReset();
        $tfaModule = $this->tfa->getModule($user);

        // check if user exists but does not have 2FA enabled
        if (!$tfaModule) {
            return true;
        } else {
            $settings = $tfaModule->getUserSettings($user);
        }
        if (!$tfaModule->enabledForUser($user, $settings)) {
            return true;
        } // check if Tfa is enabled for the current user
        if ($tfaModule->startUser($user, $settings)) {
            $key = $this->tfa->getSessionKey(true);
            $this->wire('session')->redirect($this->tfa->startUrl . '?tfa=' . $key);
        } else {
            $this->wire('session')->set('error', json_encode(['alert_dangerClass' => $this->tfa->sendCodeErrorLabel]));
            // Error creating or sending authentication code'
        }
        if ($this->wire('session')->get('error') != null) {
            $this->wire('session')->redirect($this->tfa->startUrl); // redirect back to first page
        }
    }

    /**
     * Method to hook before the mail->send() method to customize the email
     * Change the text, add a title property, change the subject, add email template if set
     * @param HookEvent $event
     * Triggered by addHookBefore WireMail::send
     * @throws WireException
     */
    protected function changeMailData(HookEvent $event)
    {
        $m = $event->object;
        $m->subject(sprintf($this->_('Your authentication code for %s'), $this->wire('config')->httpHost));
        // TODO title will not be used and displayed
        $m->title($this->_('Use this code to login into your account'));
        $this->setSenderName($m);
        $m->mailTemplate($this->input_emailTemplate);
    }

    /**
     * Grab the login code and add it to the property "logincode"
     * This code will be added to the email
     * Will be triggered by addHookBefore TfaEmail:emailCode
     * @param HookEvent $event
     * @return void
     */
    protected function getCode(HookEvent $event):void
    {
        $this->loginCode = $event->arguments(1);
        $this->setMailPlaceholder('tfacode', $this->loginCode);
        $this->setMailPlaceholder('expirationtime', (string)$this->tfa->codeExpire);
    }

    /** Add hook after emailCode to check if mail with code was sent successfully to the user
     * @param HookEvent $event - get the event object
     * Will be triggered by addHookAfter TfaEmail::emailCode
     * Clears all warnings on the backend
     * Creates an error message on the frontend if email could not be sent
     * @throws WireException
     */
    protected function checkCodeSent(HookEvent $event)
    {
        if (!$event->return) {
            $errorMsg = json_encode(['alert_dangerClass' => $this->_('Unable to send email due to possible email configuration error.')]);
            $this->wire('session')->set('error', $errorMsg);
        }
        Wire::warnings('clear all'); // remove the displaying of "A code was sent to..." message in the backend after login on the frontend
    }

    /**
     * Check if the user account is locked or not
     * @param User $user
     * @return bool
     */
    protected function checkIfAccountLocked(User $user):bool
    {
        return (!is_null($user->fl_unlockaccount));
    }

}
