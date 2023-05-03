<?php
declare(strict_types=1);

namespace FrontendLoginRegister;
//checked 27.3
/*
 * Class to create a login form
 * Supports TfaEmail too if enabled in the configuration settings
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
use ProcessWire\WireException;
use ProcessWire\WireLog as WireLog;
use ProcessWire\WireMail;
use ProcessWire\WirePermissionException;
use function ProcessWire\wireMail;

class LoginPage extends FrontendLoginRegisterPages
{

    protected Tfa $tfa; // Tfa object
    protected Link $cl; // cancel link object
    protected Link $rl; // register link object
    protected Link $pfl; // password forgotten link object

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

        // Hook to replace emailCode method entirely
        $this->addHookBefore('TfaEmail::emailCode', $this, 'emailCode');
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
        if ($this->loginregisterConfig['input_selectlogin'] === 'username') {
            $this->pfl->setLinkText($this->_('Forgot login data?'));
        } else {
            $this->pfl->setLinkText($this->_('Forgot password?'));
        }
        $this->pfl->wrap();
        return $this->pfl;
    }

    /**
     * Method to create a link to the registration page
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
     * If the account is locked via a lock code, the user can unlock his account by getting an unlock link via email
     * @param User $user
     * @param string $code
     * @return bool
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function sendAccountLockedMail(User $user, string $code):bool
    {

        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            // get the user language object as stored inside the db
            $this->stored_user_lang = $this->getSavedUserLanguage($user);

            // change user language to the stored user language placeholder in the stored user language
            $this->user->setLanguage($this->stored_user_lang);
        }

        // add placeholders !!important!!
        $this->createGeneralPlaceholders();

        // create placeholder for the email body
        $this->setMailPlaceholder('unlockaccountlink', $this->createCodeLink('fl_unlockaccountpage', $code));
        // set text for the placeholder login type
        if ($this->loginregisterConfig['input_selectlogin'] == 'email') {
            $type = $this->_('email');
        } else {
            $type = $this->_('username');
        }
        $this->setMailPlaceholder('logintype', $type);

        $m = wireMail();
        $m->to($user->email);
        $m->from($this->loginregisterConfig['input_email']);
        $this->setSenderName($m);
        $m->subject($this->_('We have detected suspicious activity on your user account'));
        $m->title($this->_('Action required to unlock your account'));
        $m->bodyHTML($this->getLangValueOfConfigField('input_unlock_account', $this->loginregisterConfig,
            $this->stored_user_lang->id).$this->___generateNoReplyText());
        $m->mailTemplate($this->loginregisterConfig['input_emailTemplate']);

        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            // set back the language to the site language
            $this->user->setLanguage($this->site_language_id);
        }

        return (bool)$m->send();

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
         Then he will be redirected from the deletion page to this page, where he has to log in first
         to be able to delete his account permanently.
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

            // log failed attempts if enabled
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
            $expireTime = $this->wire('modules')->get('TfaEmail')->codeExpire;
            if (($expireTime) && $this->wire('user')->isGuest()) {
                // show info only if it will be submitted in time and user is not logged in
                $msg .= '<br>' . sprintf($this->_('This code is still valid for %s seconds.'),
                        '<span id="expirationcounter">' . $expireTime . '</span>');
            }
            $form->getAlert()->setText($msg);
            return $form->render();
        } else {
            // render login form
            $this->setMaxAttempts(5);
            $this->setMinTime(3);
            $this->setMaxTime(3600);
            $this->setSuccessMsg($this->_('You are now logged in.'));

            if ($this->loginregisterConfig['input_selectlogin'] === 'email') {
                // add the email field
                // sanitizers added: text
                // validation rules added: email, emailDNS, required
                $email = new Email('email');
                $this->add($email);
            } else {
                // username
                // sanitizers added: pageName, text
                // validation rules added: required, usernamesyntax, uniqueusername
                $username = new Username('username');
                $username->removeRule(('uniqueUsername'));
                $this->add($username);
            }

            // pass
            // sanitizers added: text
            // validation rules added: meetsPasswordConditions, required, safePassword, matchEmail/matchUsername
            $password = new Password('password');
            if ($this->loginregisterConfig['input_selectlogin'] === 'email') {
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
            if ($this->loginregisterConfig['input_selectloginregister'] == 'loginregister') {
                $this->add($this->___registerLink());
            }

            // log failed attempts if enabled
            $this->addLogFailedLoginAttempt();

            if ($this->isValid()) {

                $content = '';

                if ($this->loginregisterConfig['input_selectlogin'] == 'email') {
                    // login with email
                    $user = $this->wire('users')->get('email=' . $this->getValue('email'));
                } else {
                    // login with username
                    $user = $this->wire('users')->get('name=' . $this->getValue('username'));
                }

                if ($this->checkIfAccountLocked($user)) {
                    // user account is locked - send the mail with the unlock account code in the stored user language
                    if ($this->sendAccountLockedMail($user, $user->fl_unlockaccount)) {
                        $this->getAlert()->removeCSSClass('alert_successClass')->setCSSClass('alert_dangerClass')->setText($this->_('Dear user! Your account is still blocked! An email with a link to unlock your account has been sent once more to your email address. Please follow the instructions inside the email to unlock your account again.'));
                    } else {
                        $this->getAlert()->removeCSSClass('alert_successClass')->setCSSClass('alert_warningClass')->setText($this->_('Dear user! Your account is still blocked! Due to technical problems an email with the unlock code could not be sent to you. Please try again a little later or contact the webmaster of this site.'));
                    }
                } else {
                    //an activation code is present -> user has not activated his account till now
                    // send a reminder mail to activate the account
                    if ($user->fl_activation) {
                        // send the reminder email in the stored user language
                        if ($this->sendReminderMail($user)) {
                            $this->getAlert()->removeCSSClass('alert_successClass')->setCSSClass('alert_warningClass')->setText($this->_('You have not verified your account yet. To activate your account, please follow the instructions inside the mail we have sent to you. Afterwards, you will be able to log in to the site.'));
                        } else {
                            $this->getAlert()->removeCSSClass('alert_successClass')->setCSSClass('alert_warningClass')->setText($this->_('You have not verified your account yet. Due to technical problems an email with an activation code could not be sent to you. Please try again a little later or contact the webmaster of this site.'));
                        }
                    } else {
                        // check if TFA is enabled in the settings
                        if ($this->loginregisterConfig['input_tfa']) {

                            $this->wire('session')->set('type',
                                'TfaEmail'); // set session for outputting message that a code was sent by email
                            $this->tfa->start($user->name, $this->getValue('pass')); // redirects
                        } else {
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
                }
            } else {
                // grab the  username or email field: depends on the login type set
                $user_field_name = $this->getAttribute('id') . '-' . $this->loginregisterConfig['input_selectlogin'];

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
                        $lock_account = false; // set to false by default

                        // check if always the same username or email was used
                        if (array_unique($keys)) {
                            // always the same email or username was used for the login attempts
                            // so check if a user exists with this username or email in the database
                            $type = ($this->loginregisterConfig['input_selectlogin'] == 'username') ? 'name' : $this->loginregisterConfig['input_selectlogin'];

                            // sanitize the values before database call
                            $value = $_POST[$this->getID() . '-' . $this->loginregisterConfig['input_selectlogin']];
                            if ($type == 'email') {
                                $sanitized_value = $this->wire('sanitizer')->email($value);
                            } else {
                                $sanitized_value = $this->wire('sanitizer')->pageName($value);
                            }
                            $checkuser = $this->wire('users')->get($type . '=' . $sanitized_value);
                            if ($checkuser->id != 0) {
                                $user = $checkuser;
                                // user was found
                                // for the rare case that the form will be submitted again to prevent creation of new code
                                // do not create and send a code once more
                                $lock_account = (!$user->fl_unlockaccount);
                            }
                        }

                        // run code only if $lock_account is true
                        if ($lock_account) {

                            // generate a random code
                            $lockCode = $this->createQueryCode(); // create the lock code

                            // send the email with the code to unlock the account and store the code inside the db
                            if ($this->sendAccountLockedMail($user, $lockCode)) {
                                // remove session blocked if present because the account has been locked
                                $this->wire('session')->remove('blocked');
                                // remove the attempts session
                                $this->wire('session')->remove('attempts');

                                // save the lock code inside the database
                                $user->setOutputFormatting(false);
                                $user->fl_unlockaccount = $lockCode; // code
                                $user->save();
                                $user->setOutputFormatting();

                                // output alert message that the account is locked now
                                $alert = $this->getAlert();
                                $alert->setCSSClass('alert_dangerClass');
                                $alert->removeCSSClass('alert_successClass');
                                $alert_text = [
                                    $this->_('Unfortunately, too many unsuccessful login attempts were made, so this user account has now been blocked for security reasons.'),
                                    $this->_('An email with a link to unlock your account has been sent to your email address.'),
                                    $this->_('Please follow the instructions inside the email to unlock your account.')
                                ];
                                $alert->setText(implode('<br>', $alert_text));

                                // do not display the form after account is locked
                                $this->showForm = false;
                            }
                        }
                    }
                }
                $content = $this->wire('page')->body;
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

            // render the form on the frontend
            $content .= parent::render();
            return $content;
        }
    }

    /**
     * Get all username/email inputs of the unsuccessful login attempts
     * Will be needed to check if login attempts are always with the same username or email
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
                case($this->loginregisterConfig['input_redirectSuccess'] == -1):
                    if ($this->wire('session')->get('prevPage') > 0) {
                        $id = (int)$this->wire('session')->get('prevPage');
                        $this->wire('session')->remove('prevPage');
                    } else {
                        $id = $current_page_id;
                    }
                    break;
                case($this->loginregisterConfig['input_redirectSuccess'] > 0):
                    $id = (int)$this->loginregisterConfig['input_redirectSuccess'];
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
        // replace the original start method completely
        $event->replace = true;

        // grab the user by email or username
        if ($this->loginregisterConfig['input_selectlogin'] == 'email') {
            // get user by email
            $user = $this->wire('users')->get('email=' . $this->wire('sanitizer')->email($this->getValue('email')));
        } else {
            // get user by username
            $user = $this->wire('users')->get('name=' . $this->wire('sanitizer')->pageName($this->wire('input')->post('login-form-username')));
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
        }

        // check if Tfa is enabled for the current user
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
     * Create time string containing hours, minutes and seconds out of seconds
     * @param int $seconds
     * @return string
     */
    protected function secondsToTime(int $seconds):string
    {
        $times = [];

        $sec = $seconds % 60;
        $times[2] = ($sec <= 9) ? '0' . $sec : (string)$sec;

        $hrs = (int)($seconds / 60);

        $minutes = $hrs % 60;
        $times[1] = ($minutes <= 9) ? '0' . $minutes : (string)$minutes;

        $hours = (int)($hrs / 60);
        $times[0] = ($hours <= 9) ? '0' . $hours : (string)$hours;

        ksort($times);
        $unit = $this->_('seconds');

        if ($times[0] == '00') {
            unset($times[0]);
        } else {
            $unit = $this->_n($this->_('hour'),
                $this->_('hours'), (int)$times[0]);
        }
        if (!isset($times[0])) {
            if ($times[1] != '00') {
                $unit = $this->_n($this->_('minute'),
                    $this->_('minutes'), (int)$times[1]);
            } else {
                unset($times[1]);
            }
        }
        return implode(':', $times) . ' ' . $unit;
    }

    /**
     * Method that replace the original emailCode method entirely
     * Uses custom text for the code email
     * @param HookEvent $event
     * @return void
     * @throws WireException
     */
    protected function emailCode(HookEvent $event):void
    {

        // replace the original emailCode method entirely
        $event->replace = true;

        // get user email, code and expiration time
        $email = $event->arguments[0];
        $code = $event->arguments[1];
        $expire = $this->wire('modules')->get('TfaEmail')->codeExpire;

        // get the current user
        $user = $this->wire('users')->get('email=' . $email);

        // get the user language object as stored inside the db
        $this->stored_user_lang = $this->getSavedUserLanguage($user);

        // change user language to the stored user language placeholder in the stored user language
        $this->user->setLanguage($this->stored_user_lang);

        // add placeholders !!important!!
        $this->createGeneralPlaceholders();

        // set placeholders for code and expiration time
        // 1) tfa code
        $this->setMailPlaceholder('tfacode', '<div id="tfacode">' . $code . '</div>');
        // 2) expiration time
        $this->setMailPlaceholder('expirationtime', $this->secondsToTime($expire));

        // create own email
        $m = new WireMail();
        $m->subject(sprintf($this->_('Your authentication code for %s'), $this->wire('config')->httpHost));
        $m->title($this->_('Use this code to login into your account'));

        $m->to($email);
        $m->from($this->loginregisterConfig['input_email']);
        $this->setSenderName($m);
        $m->bodyHTML($this->getLangValueOfConfigField('input_tfatext', $this->loginregisterConfig,
            $this->stored_user_lang->id).$this->___generateNoReplyText());
        $m->mailTemplate($this->loginregisterConfig['input_emailTemplate']);
        $code_sent = $m->send();

        // set back the language to the site language
        $this->user->setLanguage($this->site_language_id);

        if ($code_sent) {
            // create alert text to enter the code on the screen
            $alert = $this->getAlert();
            $alert->setCSSClass('alert_warningClass');
            $alert->setText($this->_('A code has been sent to you by email - please find it and enter it on this screen.'));
            $event->return = true;
        } else {
            // output an error message that the mail could not be sent
            $this->generateEmailSentErrorAlert();
            $event->return = false;
        }
    }

    /**
     * Check if the user account is locked or not
     * @param User $user
     * @return bool
     */
    protected function checkIfAccountLocked(User $user):bool
    {
        return ($user->fl_unlockaccount != '');
    }

}
