<?php

declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Base class for creating pages for the login/register process
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: FrontendLoginRegisterPages.php
 * Created: 06.07.2022
 */

use FrontendForms\Alert;
use FrontendForms\Form as Form;
use ProcessWire\Field;
use ProcessWire\FrontendForms;
use ProcessWire\Page;
use ProcessWire\User;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;
use FrontendForms\Link as Link;

class FrontendLoginRegisterPages extends Form
{
    // mail sending (sendLocalizedMail, sendReminderMail, sendDeletionConfirmationMail, sender helpers)
    use SendsLocalizedMail;
    // verification codes and links (activation/deletion/recovery links, querystring code lookup)
    use CreatesVerificationLinks;
    // form-field factory (password/email/username/language/tfa fields, generic field creation)
    use BuildsFormFields;
    // profile image upload preview and saving
    use HandlesProfileImage;

    /* properties */
    protected string $redirectSuccess = '';
    protected string $queryString = ''; // The GET parameter of the querystring
    protected array $loginregisterConfig = []; // array that holds all module configuration properties
    protected array $frontendformsConfig = []; // array that holds all module configuration properties from FrontendForms
    protected bool $useAjax = false;

    /* objects */
    protected Page $login_page; // the login page object
    protected Page $delete_page; // the delete page object
    protected Page $delete_request_page; // the delete request page object
    protected FrontendForms $frontendForms; // the FrontendForms module object
    protected \ProcessWire\Language $stored_user_lang; // the user language as stored inside the database
    protected string $tmp_profile_image_dir_path = ''; // the path to the visitor's own temp folder for the profile image upload
    protected Field $image_field; // the profile image field
    protected array $image_fields = []; // array containing all names of the image fields if present

    protected bool|string|int $prependBody = false; // select if the body text should be prepended to the form or not

    /**
     * Every form must have an ID, so let's add it via the constructor
     * @param string $id
     * @throws WireException
     * @throws WirePermissionException
     */
    public function __construct(string $id)
    {
        parent::__construct($id);

        // Use a WireTempDir named after the visitor's own session ID instead of one folder shared by
        // all visitors, so concurrent profile image uploads from different people can never land in
        // - or get copied out of - the same directory (see saveProfileImage() for the corresponding
        // single-file copy). A fixed, non-empty name makes the directory persist across requests
        // (an auto-generated/empty name would be removed again at the end of this very request),
        // so the uploaded image survives a failed form submission (fe wrong password) until the form
        // is finally submitted. It also lives under site/assets/cache/ instead of site/modules/, so
        // it is not directly reachable over HTTP like the previous tmp_profile_image folder was.
        $profileImageTempDir = $this->wire('files')->tempDir('flrProfileImage' . $this->wire('session')->id);
        $profileImageTempDir->setMaxAge(3600); // give visitors up to an hour to finish the form
        $this->tmp_profile_image_dir_path = $profileImageTempDir->get();

        // get module configuration data from FrontendLoginRegister module and create properties of each setting
        foreach ($this->wire('modules')->getConfig('FrontendLoginRegister') as $key => $value) {
            $this->loginregisterConfig[$key] = $value;
        }

        // add sender email address containing the host name to the config properties
        // this is important to send mails from shared hosts
        $this->loginregisterConfig['input_email'] = 'noreply@' . $this->wire('config')->httpHost;

        // set FrontendForms object
        $this->frontendForms = $this->wire('modules')->get('FrontendForms');

        // set configuration properties form FrontendForms module configuration
        foreach ($this->wire('modules')->getConfig('FrontendForms') as $key => $value) {
            $this->frontendformsConfig[$key] = $value;
        }

        // Fallback if input_mailmodule is not present
        if (!array_key_exists('input_mailmodule', $this->loginregisterConfig)) {
            $this->loginregisterConfig['input_mailmodule'] = 'none';
        }

        $this->login_page = $this->wire('pages')->get('template=fl_loginpage');
        $this->delete_page = $this->wire('pages')->get('template=fl_deleteaccountpage');
        $this->delete_request_page = $this->wire('pages')->get('template=fl_deleterequestpage');

        // set the value of Ajax submission depending on the module configuration
        if (array_key_exists('input_useajax', $this->loginregisterConfig)) {
            $ajax = $this->loginregisterConfig['input_useajax'];
        } else {
            $ajax = 'inherit';
        }
        if ($ajax === 'inherit') {
            if (array_key_exists('input_ajaxformsubmission', $this->frontendformsConfig)) {
                $ajax = (bool)($this->frontendformsConfig['input_ajaxformsubmission']);
            } else {
                // set Ajax submission to false
                $ajax = false;
            }
        } else {
            $ajax = (bool)$ajax;
        }
        $this->useAjax = $ajax;
    }

    /**
     * Select if you want to prepend the body to the form or not
     * If you prepend it, then the body text will not be displayed after successful form submission
     * @param bool $prepend
     * @return $this
     */
    public function setPrependBody(bool $prepend): self
    {
        $this->prependBody = $prepend;
        return $this;
    }

    /**
     * Method to prepend the body to the form by using the prepend() method.
     * This is more a type of experimental function
     * @return string
     * @throws \ProcessWire\WireException
     */
    protected function prependBody(): string
    {
        $content = '';
        if ($this->wire('page')->hasField('body')) {
            if ($this->prependBody) {
                $this->prepend($this->wire('page')->body);
            } else {
                $content = $this->wire('page')->body;
            }
        }
        return $content;
    }

    /**
     * Get the user language as stored inside the database
     * Instead of taking the site language, this method grabs the user language directly from the db
     * @param User $user
     * @return \ProcessWire\Language
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function getSavedUserLanguage(User $user): \ProcessWire\Language
    {
        if ($user->id != 0) {
            // user exists inside the database
            $languageField = $this->wire('fields')->get('language');
            $profileLanguageId = $languageField->type->loadPageField($user, $languageField)[0];
            return $this->wire('pages')->get("id=$profileLanguageId");
        } else {
            // user is guest, so take the site language
            return $user->language;
        }
    }

    /**
     * Set all user data entered inside a form to a user object
     * Will be saved later on to the user
     * @param User $user
     * @return void
     * @throws WireException
     */
    protected function setFormFieldValues(User $user): void
    {
        foreach ($this->formElements as $field) {
            // exclude password-confirm field if present
            $exclude_fields = [
                $this->getID() . '-pass-confirm',
                $this->getID() . '-oldpass'
            ];

            /*
             * check if we are on the registration page
             * if yes, exclude profile image
             */
            if (get_class($this) == 'FrontendLoginRegister\RegisterPage') {
                // check if $field is of Fieldtypeimage - exclude it on the registration page
                if (get_class($field) == 'FrontendForms\FileUploadSingle') {
                    $exclude_fields[] = $field->getAttribute('name');
                }
            }

            if (($field->getAttribute('name')) && (!in_array($field->getAttribute('name'), $exclude_fields))) {
                $field_name = $field->getAttribute('name');
                $cleaned_field_name = str_replace($this->getID() . '-', '', $field_name);
                // do not overwrite empty password fields on the profile form
                if ($cleaned_field_name != 'pass') {
                    $value = $this->getValue($field_name);
                    // add sanitizer for email and username for security reasons
                    // all other fields will be sanitized with text by default
                    // NOTE: these checks must run BEFORE the "username" -> "name" rename below,
                    // otherwise $cleaned_field_name can never still equal 'username' here
                    if ($cleaned_field_name == 'email') {
                        $value = $this->wire('sanitizer')->email($value);
                    }
                    if ($cleaned_field_name == 'username') {
                        $value = $this->wire('sanitizer')->pageName($value);
                    }
                    // username has to be changed to name as stored inside the database
                    if ($cleaned_field_name == 'username') {
                        $cleaned_field_name = 'name';
                    }
                    $user->$cleaned_field_name = $value;
                } else {
                    if ($this->getValue($field_name)) {
                        $user->$cleaned_field_name = $this->getValue($field_name);
                    }
                }
            }
        }
    }

    /**
     * Set the CSS class and the text of an alert box in a single call
     * Removes all other state classes first, so only one state (success/danger/warning) is active at a time
     * @param string $cssClass - one of 'alert_successClass', 'alert_dangerClass', 'alert_warningClass'
     * @param string $text
     * @param Alert|null $alert - the alert instance to change; defaults to the form's own alert box (getAlert())
     * @return void
     */
    protected function setAlert(string $cssClass, string $text, ?Alert $alert = null): void
    {
        $alert = $alert ?? $this->getAlert();
        $alert->removeCSSClass('alert_successClass')
            ->removeCSSClass('alert_dangerClass')
            ->removeCSSClass('alert_warningClass')
            ->setCSSClass($cssClass)
            ->setText($text);
    }

    /**
     * Set alert for the error message if there occurs an error during the storage of a user
     * @return void
     */
    public function savingUserProblemAlert(): void
    {
        $this->setAlert('alert_warningClass', $this->_('A technical problem occurred during the saving of the user data, so the user data could not be saved. Please try it once more. If the problem persists please contact the webmaster of this site.'));
    }

    /**
     * Convert the values of a text box to an array
     * Each value has to be written on a new line
     * Pure logic lives in FrontendLoginRegister\TextHelper (unit-tested there) - this
     * method just delegates so existing callers/hooks keep working unchanged.
     * @param string|null $textarea - the value of the textarea field
     * @return array
     */
    protected function newLineToArray(string|null $textarea = null): array
    {
        return TextHelper::newLineToArray($textarea);
    }

    /**
     * Method to render a link to the login page
     * @return Link
     */
    public function ___loginLink(): Link
    {
        $link = new Link('login');
        $link->setPageLink($this->login_page);
        $link->setLinkText($this->_('Login?'));
        return $link;
    }

    /**
     * Redirect to homepage in user language if a user is logged in
     * @param bool $guest - if it should be checked for guest status -> set to true, otherwise it checks for
     *     logged-in user
     * @return void
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function redirectToHomepage(bool $guest = false): void
    {
        if ($guest) {
            $true = $this->wire('user')->isGuest();
        } else {
            $true = $this->wire('user')->isLoggedIn();
        }
        if ($true) {
            if (($this->wire('languages')) && (count($this->wire('languages')) > 1)) {
                // on multi-language site
                $this->wire('session')->redirect($this->wire('pages')->get(1)->localUrl($this->wire('user')->language));
            } else {
                $this->wire('session')->redirect($this->wire('pages')->get(1)->url);
            }
        }
    }

    /**
     * Get the date format depending on the user language
     * @param User $user
     * @return string
     * @throws WireException
     */
    protected function getDateFormat(User $user): string
    {
        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            // settings for multi-language site
            if ($user->language->isDefault()) {
                $date_property = 'input_dateformat';
            } else {
                $date_property = 'input_dateformat__' . $user->language->id;
            }
            // check if a date format in the given language exists
            if (array_key_exists($date_property, $this->frontendformsConfig)) {
                return $this->frontendformsConfig[$date_property];
            } else {
                // otherwise, use the default format
                return $this->frontendformsConfig['input_dateformat'];
            }
        } else {
            return $this->frontendformsConfig['input_dateformat'];
        }
    }

    /**
     * Get the redirect url
     * @return string
     */
    protected function getRedirectSuccess(): string
    {
        return $this->redirectSuccess;
    }

    /**
     * Redirect to a certain page after successful form submission
     * @param string $url - the url of the target
     * @return FrontendLoginRegisterPages
     */
    public function setRedirectSuccess(string $url): self
    {
        $this->redirectSuccess = trim($url);
        return $this;
    }
}
