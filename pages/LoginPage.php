<?php

declare(strict_types=1);

namespace FrontendLoginRegister;

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
use FrontendForms\Button;
use FrontendForms\Email;
use FrontendForms\InputText;
use FrontendForms\Link;
use FrontendForms\Password;
use FrontendForms\Username;
use ProcessWire\HookEvent;
use ProcessWire\Tfa;
use ProcessWire\User;
use ProcessWire\WireException;
use ProcessWire\WireLog;
use ProcessWire\WirePermissionException;

class LoginPage extends FrontendLoginRegisterPages
{
    protected string|null $deletionCode = ''; // the "?deletion=..." query string, set in the constructor if a "deletion" session is active

    protected Tfa $tfa; // Tfa object
    protected Link $cl; // cancel link object
    protected Link $rl; // register link object
    protected Link $pfl; // password forgotten link object

    /**
     * Every form must have an ID, so let's add it via the constructor
     * @param string $id
     * @throws WireException
     * @throws WirePermissionException
     */
    public function __construct(string $id = 'login-form')
    {
        // Deliberately called before parent::__construct($id): Form::__construct() does a lot of
        // (comparatively expensive) setup - mail placeholders, CAPTCHA, the multi-step controller,
        // the IP ban check, several hooks, ... None of that is needed for an already-logged-in
        // visitor who is about to be redirected away immediately, so checking (and redirecting)
        // first avoids paying for all of it on every request to this page. redirectToHomepage()
        // itself only uses $this->wire(...), which does not depend on Form's constructor having
        // run, so this is safe.
        $this->redirectToHomepage(); // if a user is logged in -> no need to be here -> let's redirect to the homepage

        parent::__construct($id);

        $this->setMaxAttempts(5);
        $this->setMinTime(3);
        $this->setMaxTime(3600);
        $this->setSubmitWithAjax($this->useAjax);

        $this->tfa = new Tfa(); // instantiate a new instance of Tfa (2-factor authentication)
        $this->cl = new Link('cancelLink'); // instantiate a new instance for a link
        $this->rl = new Link('registerLink'); // instantiate a new instance for a link
        $this->pfl = new Link('passwordforgottenLink'); // instantiate a new instance for a link

        // Hook to replace start method entirely
        $this->addHookBefore('Tfa::start', $this, 'start');

        // Hook to replace emailCode method entirely
        $this->addHookBefore('TfaEmail::emailCode', $this, 'emailCode');

        if ($this->wire('session')->get('deletion')) {
            $this->deletionCode = '?' . $this->wire('session')->get('deletion');
        }
    }

    /**
     * Method to create a link to cancel the login process
     * @return Link
     * @throws WireException
     */
    public function ___cancelLink(): Link
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
    public function ___passwordForgottenLink(): Link
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
    public function ___registerLink(): Link
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
    protected function sendAccountLockedMail(User $user, string $code): bool
    {
        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            // get the user language object as stored inside the db
            $this->stored_user_lang = $this->getSavedUserLanguage($user);

            // Change the language on $this->user, NOT on $user (the account being emailed). This
            // looks backwards, but $this->user is the actual current-visitor User object (see
            // Form::initUserAndLanguage(): $this->user = $this->wire('user')), and ProcessWire's
            // _() translation calls below (subject, title, 'logintype') resolve against
            // wire('user')->language - not against an arbitrary other User object like $user.
            // Switching $user's language instead would silently leave those strings untranslated
            // in the recipient's language.
            $this->user->setLanguage($this->stored_user_lang);
        }

        // add placeholders !!important!!
        $this->createGeneralPlaceholders();

        // set text for the placeholder login type
        if ($this->loginregisterConfig['input_selectlogin'] == 'email') {
            $type = $this->_('your email');
        } else {
            $type = $this->_('your username');
        }

        $mail_sent = $this->sendLocalizedMail(
            $user,
            'input_unlock_account',
            [
                'unlockaccountlink' => $this->createCodeLink('fl_unlockaccountpage', $code),
                'logintype' => $type,
            ],
            $this->_('We have detected suspicious activity on your user account'),
            $this->_('Action required to unlock your account')
        );

        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            // set the language on $this->user (see the comment above) back to the site language
            $this->user->setLanguage($this->site_language_id);
        }

        return $mail_sent;
    }

    /**
     * Login the user and redirect to the selected page
     * @param \ProcessWire\User $user
     * @return void
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     */
    protected function defaultLogin(User $user): void
    {
        // try to log in the user
        if ($this->wire('session')->login($user->name, $this->getValue('password'))) {
            // login if tfa is not enabled
            $this->redirectAfterLogin(); // redirect after login
        } else {
            // for the rare use case that something went wrong during the session login
            $this->setAlert('alert_dangerClass', $this->_('We are sorry, but an unexpected error occurred. Please try it once more and if the problem persists, please inform the webmaster of this site.'));
        }
    }

    /**
     * Convert seconds to human-readable format (fe 200 seconds will be converted to 3 minutes and 20 seconds)
     * The month/week/day/hour/minute/second breakdown itself is pure logic living in
     * FrontendLoginRegister\TimeHelper::secondsToReadableParts() (unit-tested there); this
     * method still owns the label lookup, pluralization and "and"-joining below, since those
     * are entangled with $this->_()/$this->_n() translation calls.
     * @param int $ss
     * @return string
     */
    public function secondsToReadable(int $ss): string
    {
        $bit = TimeHelper::secondsToReadableParts($ss);

        $labelSingular = [
            'month' => $this->_('month'),
            'week' => $this->_('week'),
            'day' => $this->_('day'),
            'hour' => $this->_('hour'),
            'minute' => $this->_('minute'),
            'second' => $this->_('second')
        ];

        $labelPlural = [
            'month' => $this->_('months'),
            'week' => $this->_('weeks'),
            'day' => $this->_('days'),
            'hour' => $this->_('hours'),
            'minute' => $this->_('minutes'),
            'second' => $this->_('seconds')
        ];

        $ret = [];
        foreach ($bit as $k => $v) {
            $number = explode(' ', (string)$v);
            if ($number[0] != 0) {
                $label = $this->_n($labelSingular[$k], $labelPlural[$k], $v);
                $ret[] = $v . ' ' . $label;
            }
        }

        if (count($ret) > 1) {
            array_splice($ret, count($ret) - 1, 0, $this->_('and'));
        }
        return implode(' ', $ret);
    }

    /**
     * Render the form
     * Renders 2 forms - one for password and email/username and another for entering the authentication code if
     * enabled
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     * @throws \DOMException
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    public function render(): string
    {
        $content = '';

        if (!$this->getSubmitWithAjax()) {
            $content .= $this->prependBody();
        }

        /*
         Check if "deletion" session from deletion link is active
         This is the case if a user clicks on the deletion link inside the email and is not logged in,
         then he will be redirected from the deletion page to this page, where he has to log in first
         to be able to delete his account permanently.
         */

        if (($this->deletionCode) && (!$this->wire('session')->get('showed'))) {
            $this->wire('session')->set('showed', '1');
            // user comes from the redirect of the "delete account page", but was not logged in
            $this->setAlert('alert_warningClass', $this->_('You have to log in to delete your account.'));
        }

        // check if the tfa session is active and render the second form
        if ($this->tfa->active()) {
            return $this->renderTfaCodeForm($content);
        }

        return $this->renderCredentialsForm($content);
    }

    /**
     * Render the second step of the login process: entering the TFA authentication code
     * Called from render() when a Tfa session is active
     * @param string $content - markup already collected before this branch (fe the prepended body text)
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     * @throws Exception
     */
    protected function renderTfaCodeForm(string $content): string
    {
        $tfaSession = $this->wire('session')->get('tfa'); // grab the tfa session
        $module = $this->tfa->getModule(); // get the TFA module object which is used by the user who currently tries to log in
        $user = $this->wire('users')->get($tfaSession['id']); // grab the user by session id

        // 1) check if the session key is valid -
        // This code is copied from the process() method inside the Tfa.php

        $key = $this->wire('sanitizer')->text($this->wire('input')->get('tfa'));
        $tfaUser = $this->tfa->getUser();
        // if invalid key, abort and go back to the first page
        if (empty($key) || $key !== $this->tfa->getSessionKey() || (!$tfaUser instanceof User) || $tfaUser->isLoggedin()) {
            $this->wire('session')->set(
                'error',
                json_encode(['alert_dangerClass' => $this->_('Your session key is not valid.')])
            );
            $this->tfa->sessionReset($this->tfa->startUrl);
            return $content;
        }

        // 2) check if the time for authentication code is expired -
        // This piece of code is copied from the process() method inside the Tfa.php

        $initTime = $this->wire('session')->get('tfa')['time'];
        if (!$initTime || (time() - $initTime > $module->codeExpire)) {
            // more than the time set has passed since authentication, so make them start over
            $this->wire('session')->set(
                'error',
                json_encode(['alert_warningClass' => $this->_('Your code has expired. Please enter your data once more to get a new code.')])
            );
            $this->tfa->sessionReset($this->tfa->startUrl);
            return $content;
        }

        // Create the form for the authentication code

        $this->setSuccessMsg($this->_('You are now logged in.'));
        $this->setAttribute('action', $this->wire('page')->url . '?tfa=' . $this->tfa->getSessionKey(true));
        $this->disableCaptcha(); // disable Captcha by default - no need for it

        // authentication code input field
        $tfaCode = new InputText('tfa_code');
        $tfaCode->setLabel($this->tfa->inputLabel);
        $tfaCode->setSanitizer('trim');
        $tfaCode->setRule('required');
        $tfaCode->setRule(
            'checkTfaCode',
            $user,
            $module
        )->setCustomMessage($this->_('The authentication code is not correct.'));
        $this->add($tfaCode);

        // submit button
        $button = new Button('tfa_submit');
        $button->setAttribute('value', $this->_('Send'));
        $this->add($button);

        // show or hide the cancel link depending on settings
        if ($this->tfa->showCancel) {
            $this->add($this->cancelLink());
        }

        // log failed attempts if enabled
        $this->addLogFailedLoginAttempt();

        if ($this->getSubmitWithAjax()) {
            // redirect to the page, which has been selected inside the module configuration
            $this->setRedirectUrlAfterAjax($this->wire('pages')->get($this->getRedirectPageIdAfterLogin())->url . $this->deletionCode);
        }

        if ($this->isValid()) {
            $this->wire('session')->remove('showed');
            $this->tfa->sessionReset(); // remove all tfa session values
            $this->wire('session')->forceLogin($user); // force login
            $this->redirectAfterLogin(); // redirect after login if submission was not done via Ajax
        }

        $msg = $this->getAlert()->getText() ? $this->getAlert()->getText() : ''; // grab alert text if present

        // Only if TfaEmail module is used
        if (($module->className() == 'TfaEmail') && ($this->wire('session')->get('type'))) {
            // only on redirect from step 1 to 2
            $msg .= $this->_('A code has been sent to you by email - please enter it in the input field below.');
            $this->getAlert()->setCSSClass('alert_successClass');
            $this->wire('session')->remove('type');
        }

        // add info about how long the code will be valid - use the actually-active TFA module
        // ($module, resolved above) rather than hardcoding TfaEmail, so the countdown always
        // matches the same module the expiration check above actually enforces against
        $expireTime = $module->codeExpire;
        if (($expireTime) && $this->wire('user')->isGuest()) {
            // show info only if it will be submitted in time and the user is not logged in
            $secondsLeft = $this->_('seconds left'); // plural
            $secondLeft = $this->_('second left'); // singular
            // #timecounter is ticked down live client-side by startExpirationCountdown() in
            // assets/js/frontendloginregister-frontend.js, starting from data-time seconds. This is a purely visual
            // aid - the code's actual expiration is still enforced server-side (see the "time for
            // authentication code is expired" check above), independently of what this shows.
            $timerMarkup = '<span id="minTime" data-time="' . $expireTime . '" data-unit="' . $secondsLeft . ';' . $secondLeft . '">' . $this->secondsToReadable($expireTime) . '</span>'
                . '<span id="timecounter" class="expiration-counter" data-time="' . $expireTime . '" data-unit-singular="' . htmlspecialchars($secondLeft, ENT_QUOTES) . '" data-unit-plural="' . htmlspecialchars($secondsLeft, ENT_QUOTES) . '">' . $expireTime . ' ' . $secondsLeft . '</span>';
            $msg .= '<br>' . sprintf($this->_('This code is valid for %s.'), $timerMarkup);
        }
        $this->getAlert()->setText($msg);

        // render the form on the frontend
        return $content . parent::render();
    }

    /**
     * Render the first step of the login process: username/email + password
     * Called from render() when no Tfa session is active
     * @param string $content - markup already collected before this branch (fe the prepended body text)
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     * @throws \DOMException
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    protected function renderCredentialsForm(string $content): string
    {
        // login form
        $this->setSuccessMsg($this->_('You are now logged in.'));

        if ($this->loginregisterConfig['input_selectlogin'] === 'email') {
            // add the email field
            // sanitizers added: text
            // validation rules added: email, emailDNS, required
            $email = new Email('email');
            $email->setRule('required');
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
        // no need to show password requirements here
        $password->showPasswordRequirements(false);
        $password->showPasswordToggle(!$this->loginregisterConfig['input_hide_passwordtoggle']);
        $this->add($password);

        // button
        $button = new Button('submit');
        $button->setAttribute('value', $this->_('Login'));
        $this->add($button);

        // password forgotten link
        $this->add($this->passwordForgottenLink());

        // register link
        if ($this->loginregisterConfig['input_selectloginregister'] == 'loginregister') {
            $this->add($this->registerLink());
        }

        // log failed attempts if enabled
        $this->addLogFailedLoginAttempt();

        // Set the redirect url for the Javascript redirect if Tfa is not enabled
        if ($this->getSubmitWithAjax()) {
            if ((!$this->loginregisterConfig['input_tfa']) || ($this->deletionCode)) {
                $this->setRedirectUrlAfterAjax($this->wire('pages')->get($this->getRedirectPageIdAfterLogin())->url . $this->deletionCode);
            }
        }

        if ($this->isValid()) {
            if ($this->loginregisterConfig['input_selectlogin'] == 'email') {
                // login with email
                $email = $this->wire('sanitizer')->selectorValue($this->getValue('email'));
                $user = $this->wire('users')->get('email=' . $email);
            } else {
                // login with username
                $username = $this->wire('sanitizer')->selectorValue($this->getValue('username'));
                $user = $this->wire('users')->get('name=' . $username);
            }

            $this->handleValidLogin($user);
        } else {
            $content .= $this->handleFailedLoginAttempt();
        }

        // create error messages from session if present
        if ($this->wire('session')->get('error')) {
            $errorMsg = json_decode($this->wire('session')->get('error'), true);
            // get the first key in the error array
            reset($errorMsg);
            $key = key($errorMsg);
            $this->setAlert($key, $errorMsg[$key]);
            $this->wire('session')->remove('error');
        }

        // render the form on the frontend
        return $content . parent::render();
    }

    /**
     * Handle a successfully validated login form submission: locked account, pending activation,
     * TFA start, or the default login
     * Extracted from render() to keep the credentials-step branch readable
     * @param User $user
     * @return void
     * @throws WireException
     * @throws WirePermissionException
     * @throws \DOMException
     * @throws \DateMalformedStringException
     */
    protected function handleValidLogin(User $user): void
    {
        if ($this->checkIfAccountLocked($user)) {
            // user account is locked - send the mail with the unlock account code in the stored user language
            if ($this->sendAccountLockedMail($user, $user->fl_unlockaccount)) {
                $this->setAlert('alert_dangerClass', $this->_('Dear user! Your account is still blocked! An email with a link to unlock your account has been sent once more to your email address. Please follow the instructions inside the email to unlock your account again.'));
            } else {
                $this->setAlert('alert_warningClass', $this->_('Dear user! Your account is still blocked! Due to technical problems an email with the unlock code could not be sent to you. Please try again a little later or contact the webmaster of this site.'));
            }
        } else {
            // an activation code is present -> user has not activated his account till now
            // send a reminder mail to activate the account
            if ($user->fl_activation) {
                // send the reminder email in the stored user language
                if ($this->sendReminderMail($user)) {
                    $this->setAlert('alert_warningClass', $this->_('You have not verified your account yet. To activate your account, please follow the instructions inside the mail we have sent to you. Afterwards, you will be able to log in to the site.'));
                } else {
                    $this->setAlert('alert_warningClass', $this->_('You have not verified your account yet. Due to technical problems an email with an activation code could not be sent to you. Please try again a little later or contact the webmaster of this site.'));
                }
            } else {
                // check if TFA is enabled in the settings
                if ($this->loginregisterConfig['input_tfa']) {
                    // check if TFA is enabled for the given user
                    if ($user->hasTfa()) {
                        // set session for the outputting message that a code was sent by email
                        $this->wire('session')->set('type', 'TfaEmail');
                        $this->tfa->start($user->name, $this->getValue('password')); // redirects if Ajax is not enabled
                    } else {
                        $this->wire('session')->remove('showed');
                        $this->defaultLogin($user);
                    }
                } else {
                    $this->defaultLogin($user);
                }
            }
        }
    }

    /**
     * Handle an invalid login form submission: detect and act on too many failed login attempts
     * (lock the account and send the unlock mail), and re-collect the prepended body text on Ajax submissions
     * Extracted from render() to keep the credentials-step branch readable
     * @return string - additional markup to append to the form's content (fe the re-prepended body text)
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function handleFailedLoginAttempt(): string
    {
        $content = '';

        // grab the username or email field: depends on the login type set
        $user_field_name = $this->getAttribute('id') . '-' . $this->loginregisterConfig['input_selectlogin'];

        if ($this->wire('session')->get($user_field_name)) {
            $keys = $this->arrayKeysMulti($this->wire('session')->get($user_field_name));

            // set lock if $session blocked is set - the constructor always calls
            // setMaxAttempts(5), so getMaxAttempts() is always truthy here; there used to be a
            // fallback "else" branch for when maxAttempts is not set, but it could never be
            // reached and has been removed
            $limit_reached = (!is_null($this->wire('session')->get('blocked')));
            if ($limit_reached) {
                $lock_account = false; // set to false by default

                // check if always the same username or email was used
                if (count(array_unique($keys)) === 1) {
                    // the same email or username was always used for the login attempts
                    // so check if a user exists with this username or email in the database
                    $type = ($this->loginregisterConfig['input_selectlogin'] == 'username') ? 'name' : $this->loginregisterConfig['input_selectlogin'];

                    // sanitize the values before database call
                    $value = $this->wire('input')->post(
                        $this->getID() . '-' . $this->loginregisterConfig['input_selectlogin']
                    );
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
                        // remove the attempts' session
                        $this->wire('session')->remove('attempts');

                        // save the lock code inside the database
                        $user->setOutputFormatting(false);
                        $user->fl_unlockaccount = $lockCode; // code
                        $user->save();
                        $user->setOutputFormatting();

                        // output alert message that the account is locked now
                        $alert_text = [
                            $this->_('Unfortunately, too many unsuccessful login attempts were made, so this user account has now been blocked for security reasons.'),
                            $this->_('An email with a link to unlock your account has been sent to your email address.'),
                            $this->_('Please follow the instructions inside the email to unlock your account.')
                        ];
                        $this->setAlert('alert_dangerClass', implode('<br>', $alert_text));

                        // do not display the form after the account is locked
                        $this->showForm = false;
                    }
                }
            }
        }
        if ($this->getSubmitWithAjax()) {
            $content .= $this->prependBody();
        }

        return $content;
    }

    /**
     * Get all username/email inputs of the unsuccessful login attempts
     * Will be needed to check if login attempts are always with the same username or email
     * Pure logic lives in FrontendLoginRegister\ArrayHelper::arrayKeysMulti() (unit-tested
     * there) - this method just delegates so the existing call site keeps working unchanged.
     * @param array $array - the session array
     * @return array
     */
    protected function arrayKeysMulti(array $array): array
    {
        return ArrayHelper::arrayKeysMulti($array);
    }

    /**
     * Write a log if failed login attempts have reached the max number of attempts
     * @return void
     * @throws WireException
     */
    protected function addLogFailedLoginAttempt(): void
    {
        // check if logging is enabled and max attempts are set
        if (($this->frontendForms->input_logFailedAttempts) && ($this->getMaxAttempts())) {
            // check if the max number of attempts is reached
            if (($this->getMaxAttempts()) && ($this->frontendForms->input_logFailedAttempts)) {
                if (($this->getMaxAttempts() - $this->wire('session')->attempts) === 0) {
                    // write the log file
                    $log = new WireLog();
                    $ip_address = $this->wire('session')->getIP();
                    $log->save('failed-logins', 'IP: ' . $ip_address);
                }
            }
        }
    }

    /**
     * Get the deletion query string if it is present
     * @return string
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     */
    protected function getDeletionQueryString(): string
    {
        $query_string = '';
        // check if 'deletion' session is present
        if ($this->wire('session')->get('deletion')) {
            $query_string = '?' . $this->wire('session')->get('deletion');
        }
        return $query_string;
    }

    /**
     * Get the id of the page where it should be redirected after successfull form submission
     * @return int
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     */
    protected function getRedirectPageIdAfterLogin(): int
    {
        // check if 'deletion' session is present
        if ($this->getDeletionQueryString() != '') {
            $id = $this->delete_page->id;
        } else {
            // get redirect page from module configuration settings
            $current_page_id = $this->wire('pages')->get('template=fl_loginpage')->id;
            switch (true) {
                case ($this->loginregisterConfig['input_redirectSuccess'] == -1):
                    if ($this->wire('session')->get('prevPage') > 0) {
                        $id = (int)$this->wire('session')->get('prevPage');
                        $this->wire('session')->remove('prevPage');
                    } else {
                        $id = $current_page_id;
                    }
                    break;
                case ($this->loginregisterConfig['input_redirectSuccess'] > 0):
                    $id = (int)$this->loginregisterConfig['input_redirectSuccess'];
                    break;
                default:
                    $id = $current_page_id;
            }
        }
        return (int)$id;
    }

    /**
     * Redirect after successful login to a pre-defined page from the settings
     * @return void
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function redirectAfterLogin(): void
    {
        $id = $this->getRedirectPageIdAfterLogin();
        $query_string = $this->getDeletionQueryString();

        $this->wire('session')->set('loginID', $id);
        if (($this->wire('languages')) && (count($this->wire('languages')) > 1)) {
            $redirectUrl = $this->wire('pages')->get($id)->localUrl($this->wire('user')->language) . $query_string;
        } else {
            $redirectUrl = $this->wire('pages')->get($id)->url . $query_string;
        }

        // set data attribute if Ajax call
        if ($this->getSubmitWithAjax()) {
            $this->setRedirectUrlAfterAjax($redirectUrl);
        } else {
            // otherwise redirect to a given page
            $this->wire('session')->redirect($redirectUrl);
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
            $user = $this->wire('users')->get('email=' . $this->wire('sanitizer')->selectorValue($this->getValue('email')));
        } else {
            // get user by username
            $usernameField = $this->getID() . '-username';
            $user = $this->wire('users')->get(
                'name=' . $this->wire('sanitizer')->pageName($this->wire('input')->post($usernameField))
            );
        }

        // check if user is not allowed to log in
        if (!$this->wire('session')->allowLogin($user->name, $user)) {
            $this->wire('session')->set(
                'error',
                json_encode(['alert_dangerClass' => $this->_('Sorry, but you are not allowed to log in.')])
            );
            return;
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
            // Error creating or sending the authentication code
        }
        if ($this->wire('session')->get('error') != null) {
            $this->wire('session')->redirect($this->tfa->startUrl); // redirect back to first page
        }
    }

    /**
     * Create time string containing hours, minutes and seconds out of seconds
     * The hours/minutes/seconds breakdown itself is pure logic living in
     * FrontendLoginRegister\TimeHelper::secondsToTimeParts() (unit-tested there); this method
     * still owns the zero-padding, leading-component-dropping and translation-unit-selection
     * below, since those are entangled with $this->_()/$this->_n() translation calls.
     * @param int $seconds
     * @return string
     */
    protected function secondsToTime(int $seconds): string
    {
        $times = [];
        $parts = TimeHelper::secondsToTimeParts($seconds);

        $times[2] = ($parts['seconds'] <= 9) ? '0' . $parts['seconds'] : (string)$parts['seconds'];
        $times[1] = ($parts['minutes'] <= 9) ? '0' . $parts['minutes'] : (string)$parts['minutes'];
        $times[0] = ($parts['hours'] <= 9) ? '0' . $parts['hours'] : (string)$parts['hours'];

        ksort($times);
        // default unit if hours and minutes both end up unset below (seconds-only case) -
        // correctly singular/plural, unlike the hours/minutes units this used to fall back on
        $unit = $this->_n($this->_('second'), $this->_('seconds'), $parts['seconds']);

        if ($times[0] == '00') {
            unset($times[0]);
        } else {
            $unit = $this->_n(
                $this->_('hour'),
                $this->_('hours'),
                (int)$times[0]
            );
        }
        if (!isset($times[0])) {
            if ($times[1] != '00') {
                $unit = $this->_n(
                    $this->_('minute'),
                    $this->_('minutes'),
                    (int)$times[1]
                );
            } else {
                unset($times[1]);
            }
        }
        return implode(':', $times) . ' ' . $unit;
    }

    /**
     * Method that replaces the original emailCode() method entirely
     * Uses custom text for the code email
     * @param HookEvent $event
     * @return void
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function emailCode(HookEvent $event): void
    {
        // replace the original emailCode method entirely
        $event->replace = true;

        // get user email, code and expiration time
        $email = $this->wire('sanitizer')->selectorValue($event->arguments[0]);
        $code = $event->arguments[1];
        $expire = $this->wire('modules')->get('TfaEmail')->codeExpire;

        // get the current user - $email was already sanitized above, do not sanitize it again
        $user = $this->wire('users')->get('email=' . $email);

        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            // get the user language object as stored inside the db
            $this->stored_user_lang = $this->getSavedUserLanguage($user);

            // Change the language on $this->user, NOT on $user (the account the code is emailed
            // to). This looks backwards, but $this->user is the actual current-visitor User
            // object (see Form::initUserAndLanguage(): $this->user = $this->wire('user')), and
            // ProcessWire's _() translation calls below (subject, title) resolve against
            // wire('user')->language - not against an arbitrary other User object like $user.
            // Switching $user's language instead would silently leave those strings untranslated
            // in the recipient's language.
            $this->user->setLanguage($this->stored_user_lang);
        }

        // add placeholders !!important!!
        $this->createGeneralPlaceholders();

        $code_sent = $this->sendLocalizedMail(
            $user,
            'input_tfatext',
            [
                'tfacode' => '<div id="tfacode">' . $code . '</div>',
                'expirationtime' => $this->secondsToTime($expire),
            ],
            sprintf($this->_('Your authentication code for %s'), $this->wire('config')->httpHost),
            $this->_('Use this code to login into your account')
        );

        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            // set the language on $this->user (see the comment above) back to the site language
            $this->user->setLanguage($this->site_language_id);
        }

        if ($code_sent) {
            // create alert text to enter the code on the screen
            $this->setAlert('alert_warningClass', $this->_('A code has been sent to you by email - please find it and enter it on this screen.'));
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
    protected function checkIfAccountLocked(User $user): bool
    {
        return ($user->fl_unlockaccount != '');
    }

    /**
     * Return the rendered form markup
     * @return string
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     * @throws \DOMException
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
