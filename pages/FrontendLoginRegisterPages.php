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

use DateTime;
use Exception;
use FrontendForms\Email as Email;
use FrontendForms\FieldsetClose as FieldsetClose;
use FrontendForms\FieldsetOpen as FieldsetOpen;
use FrontendForms\Form as Form;
use FrontendForms\InputCheckbox;
use FrontendForms\InputFields;
use FrontendForms\InputPassword as InputPassword;
use FrontendForms\InputRadioMultiple as InputRadioMultiple;
use FrontendForms\Language;
use FrontendForms\Password as Password;
use FrontendForms\PasswordConfirmation as PasswordConfirmation;
use FrontendForms\Username as Username;
use ProcessWire\Field;
use ProcessWire\FrontendForms;
use ProcessWire\Page;
use ProcessWire\User;
use ProcessWire\WireException;
use ProcessWire\WireMail;
use ProcessWire\WirePermissionException;
use FrontendForms\Link as Link;
use function ProcessWire\__;
use function ProcessWire\wirePopulateStringTags;

class FrontendLoginRegisterPages extends Form
{

    /*properties*/
    protected string $redirectSuccess = '';
    protected string $queryString = ''; // The get parameter of the querystring
    protected array $loginregisterConfig = []; // array that holds all module configuration properties
    protected array $frontendformsConfig = []; // array that holds all module configuration properties from FrontendForms
    protected bool $useAjax = false;
    /*objects*/
    protected Page $login_page; // the login page object
    protected Page $delete_page; // the delete page object
    protected Page $delete_request_page; // the delete request page object
    protected FrontendForms $frontendForms; // the FrontendForms module object
    protected \ProcessWire\Language $stored_user_lang; // the user language as stored inside the database
    protected string $tmp_profile_image_dir_path = ''; // the path to the temp folder for the profile image upload
    protected Field $image_field; // the profile image field
    protected array $image_fields = []; // array containing all names of the image fields if present
    protected string $moduleversion = ''; // get the current version of FrontendForms
    protected bool $needUpdate = false; // should FrontendForms be updated to support certain functions
    /**
     * Every form must have an id, so let's add it via the constructor
     * @param string $id
     * @throws WireException
     */
    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->moduleversion = $this->wire('modules')->getModuleInfo('FrontendForms')['version'];

        $versionCompare = version_compare($this->moduleversion, '2.1.45');
        if($versionCompare < 0){
            $this->needUpdate = true;
        }

        // set tmp_profile_image_dir_path
        $this->tmp_profile_image_dir_path = $this->wire('config')->paths->siteModules . 'FrontendLoginRegister/tmp_profile_image/';

        //check if temp folder exists, otherwise create it
        if (!$this->wire('files')->exists($this->tmp_profile_image_dir_path, 'dir')) {
            $this->wire('files')->mkdir($this->tmp_profile_image_dir_path);
        }


        // get module configuration data from FrontendLoginRegister module and create properties of each setting
        foreach ($this->wire('modules')->getConfig('FrontendLoginRegister') as $key => $value) {
            $this->loginregisterConfig[$key] = $value;
        }

        // set FrontendForms object
        $this->frontendForms = $this->wire('modules')->get('FrontendForms');

        // set configuration properties form FrontendForms module configuration
        foreach ($this->wire('modules')->getConfig('FrontendForms') as $key => $value) {
            $this->frontendformsConfig[$key] = $value;
        }

        $this->login_page = $this->wire('pages')->get('template=fl_loginpage');
        $this->delete_page = $this->wire('pages')->get('template=fl_deleteaccountpage');
        $this->delete_request_page = $this->wire('pages')->get('template=fl_deleterequestpage');

        // set value of Ajax submission depending on the module configuration
        if(array_key_exists('input_useajax',$this->loginregisterConfig)){
            $ajax = $this->loginregisterConfig['input_useajax'];
        } else {
            $ajax = 'inherit';
        }
        if($ajax === 'inherit'){
            if(array_key_exists('input_ajaxformsubmission',$this->frontendformsConfig)){
                $ajax= (bool)($this->frontendformsConfig['input_ajaxformsubmission']);
            } else {
                // set Ajax submission to false
                $ajax = false;
            }
        } else {
            $ajax = (bool)$ajax;
        }
        $this->useAjax = $ajax;

        // Ajax form submission will not be supported by the current FrontendForms version
        if($this->needUpdate){
            $this->useAjax = false;
        }
    }


    /**
     * Save an uploaded image to an user
     * @param User $user
     * @param string $form_name
     * @return void
     * @throws WireException
     */
    protected function saveProfileImage(User $user, string $form_name):void
    {

        $user->of(false);

        // set the path to the final user asset/files folder with the id of the user
        $dist_upload_path = $this->wire('config')->paths->assets . 'files/' . $user->id . '/';

        // just for the case that there is more than 1 image field present
        foreach ($this->image_fields as $fieldname) {

            $file_name = $this->getValue($fieldname); // filename of the uploaded image if present

            // run code afterward only if image was uploaded
            if ($file_name) {

                // delete old user images from this field in the db first
                $user->$fieldname->deleteAll();

                // copy the image from the tmp folder to the appropriate assets/files folder of the user
                $this->wire('files')->copy($this->tmp_profile_image_dir_path, $dist_upload_path);

                // unlink this file inside the tmp folder
                $this->wire('files')->unlink($this->tmp_profile_image_dir_path . $file_name);

                if ($form_name == 'register-form') {
                    // save the image to the database
                    $user->$fieldname = $file_name;
                    $user->save($fieldname);
                }

            } else {
                // check if remove image checkbox is checked
                if (isset($_POST[$this->getID() . '-' . $fieldname . '-remove'])) {
                    $user->$fieldname->deleteAll();// delete the image
                }
            }
        }
    }

    /**
     * Get the user language as stored inside the database
     * Instead of taking the site language, this method grabs the user language directly from the db
     * @param User $user
     * @return \ProcessWire\Language
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function getSavedUserLanguage(User $user):\ProcessWire\Language
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
    protected function setFormFieldValues(User $user):void
    {
        foreach ($this->formElements as $field) {
            // exclude password-confirm field if present
            $exclude_fields = [
                $this->getID() . '-pass-confirm',
                $this->getID() . '-oldpass'
            ];

            /*
             * check if we are on registration page
             * if yes, exclude profile image
             */
            if (get_class($this) == 'FrontendLoginRegister\RegisterPage') {
                // check if $field is of Fieldtypeimage - exclude it on registration page
                if (get_class($field) == 'FrontendForms\FileUploadSingle') {
                    $exclude_fields[] = $field->getAttribute('name');
                }
            }


            if (($field->getAttribute('name')) && (!in_array($field->getAttribute('name'), $exclude_fields))) {
                $field_name = $field->getAttribute('name');
                $cleaned_field_name = str_replace($this->getID() . '-', '', $field_name);
                // username has to be changed to name as stored inside the database
                if ($cleaned_field_name == 'username') {
                    $cleaned_field_name = 'name';
                }
                // do not overwrite empty password fields on profile form
                if ($cleaned_field_name != 'pass') {
                    $value = $this->getValue($field_name);
                    // add sanitizer for email and username for security reasons
                    // all other fields will be sanitized with text by default
                    if ($cleaned_field_name == 'email') {
                        $value = $this->wire('sanitizer')->email($value);
                    }
                    if ($cleaned_field_name == 'username') {
                        $value = $this->wire('sanitizer')->pageName($value);
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
     * link the ProcessWire Fieldtype classes to the FrontendForms classes
     * This is necessary to create form fields which are set in the user template on the frontend with the appropriate FrontendForms classes
     */
    protected array $linkingFieldTypes = [
        'FieldtypeEmail' => 'InputText',
        'FieldtypePassword' => 'InputPassword',
        'FieldtypeText' => 'InputText',
        'FieldtypeTextarea' => 'Textarea',
        'FieldtypeCheckbox' => 'InputCheckbox',
        'FieldtypeInteger' => 'InputNumber',
        'FieldtypeFloat' => 'InputText',
        'FieldtypeDatetime' => 'InputDateTime',
        'FieldtypeURL' => 'InputUrl',
        'FieldtypePage' => 'InputText',
        'FieldtypeImage' => 'FileUploadSingle',
        'FieldtypeCroppableImage3' => 'FileUploadSingle'
    ];

    /**
     * Calculate the difference in days between now and the deletion date
     * @param User $user
     * @return int
     */
    protected function daysToDelete(User $user):int
    {
        // registration date
        $registration = new DateTime();
        $registration->setTimestamp($user->created);

        // current date
        $current = new DateTime();
        $current->setTimestamp(time());

        // get difference between now and registration date in days
        $diff = $registration->diff($current);
        $diff_days = $diff->format('%a');

        return ((int)$diff_days + $this->loginregisterConfig['input_delete']);
    }

    /**
     * Create an activation link
     * @param User $user
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function createActivationLink(User $user):string
    {
        return $this->createCodeLink('fl_activationpage', $user->fl_activation, null,
            $this->_('I have registered for an account - activate my account now'));
    }

    /**
     * Create a deletion link if user has not registered
     * @param User $user
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function createNotRegisteredLink(User $user):string
    {
        return $this->createCodeLink('fl_activationpage', $user->fl_activation, 'notregisteredcode',
            $this->_('I have not registered for an account - delete the account'));
    }

    /**
     * Base method for creating a link with a random code
     * @param string $templateName
     * @param string $code - the random code itself
     * @param string|null $querystringname
     * @param string|null $linktext
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function createCodeLink(
        string $templateName,
        string $code,
        string|null $querystringname = null,
        string|null $linktext = null
    ):string {
        $targetPage = $this->wire('pages')->get('template=' . $templateName . ',include=all');
        if (is_null($querystringname)) {
            $codeName = substr($templateName, 3);// remove  "fL_"
            $codeName = str_replace('page', 'code', $codeName); // replace the string "Page" with "code"
        } else {
            $codeName = $querystringname;
        }
        $link = new Link();
        $link->setUrl($targetPage->httpUrl);
        $link->setQueryString($codeName . '=' . $code);
        $linkt_text = (!is_null($linktext)) ? $linktext : $targetPage->httpUrl;
        $link->setLinkText($linkt_text);
        return $link->___render();
    }

    /**
     * Create a centered do not replay info at the footer of the email body
     * @return string
     */
    protected function ___generateNoReplyText():string
    {
        return '<br><table class="footer" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-top: 1px solid #ddd;">
                  <tr>
                    <td class="center" align="center" valign="top">
                      <center>
                        <table class="container">
                            <tr>
                              <td><br>
                                [[DONOTREPLAYVALUE]]
                              </td>
                          </tr>
                        </table>
                      </center>
                    </td>
                  </tr>
                </table>';
    }

    /**
     * Send a reminder mail to the user if account is not activated
     * @param User $user
     * @return bool
     * @throws WireException
     */
    protected function sendReminderMail(User $user):bool
    {

        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            // get the user language object as stored inside the db
            $this->stored_user_lang = $this->getSavedUserLanguage($user);

            // change user language to the stored user language placeholder in the stored user language
            $this->user->setLanguage($this->stored_user_lang);
        }

        // add placeholders !!important!!
        $this->createGeneralPlaceholders();

        $days_to_delete = $this->daysToDelete($user);

        $date_to_delete_ts = (new DateTime('NOW'))->modify('+' . $days_to_delete . ' days')->getTimestamp();

        // create placeholder variables

        // 1) registration date
        $this->setMailPlaceholder('registrationdate',
            $this->wire('datetime')->date($this->getDateFormat($user), $user->created));

        // 2) deletion date
        $this->setMailPlaceholder('deletedate',
            $this->wire('datetime')->date($this->getDateFormat($user), $date_to_delete_ts));

        // 3) verification link
        $this->setMailPlaceholder('verificationlink', $this->createActivationLink($user));

        // 4) not registered link
        $this->setMailPlaceholder('notregisteredlink', $this->createNotRegisteredLink($user));

        $m = new WireMail();
        $m->to($user->email);
        $m->from($this->loginregisterConfig['input_email']);
        $this->setSenderName($m);
        $m->subject($this->_('Action required to activate your account'));
        $m->title($this->_('Have you forgotten to verify your account?'));

        // set placeholder for title
        $this->setMailPlaceholder('title', $m->title);

        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            $text = $this->getLangValueOfConfigField('input_remindertext', $this->loginregisterConfig,
                $this->stored_user_lang->id);
        } else {
            $text = $this->loginregisterConfig['input_remindertext'];
        }

        $body = $this->generateEmailPreHeader($m) . $text . $this->___generateNoReplyText();
        $body = wirePopulateStringTags($body, $this->getMailPlaceholders(), ['tagOpen' => '[[', 'tagClose' => ']]']);
        $this->setMailPlaceholder('body', $body);
        $m->bodyHTML($body);
        $m->mailTemplate($this->loginregisterConfig['input_emailTemplate']);
        $this->includeMailTemplate($m);
        $m->mailTemplate('none'); // important to prevent double inclusion of HTML template on hooks

        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            // set back the language to the site language
            $this->user->setLanguage($this->site_language_id);
        }

        return (bool)$m->send();
    }

    /**
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function sendDeletionConfirmationMail(User $user):bool
    {
        if (!$this->loginregisterConfig['input_prevent_send_deletion_email']) {

            if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                // get the id of the user language as stored inside the db
                $this->stored_user_lang = $user->language;

                // change user language to the stored user language placeholder in the stored user language
                $this->user->setLanguage($this->stored_user_lang);
            }

            // add placeholders !!important!!
            $this->createGeneralPlaceholders();

            // create placeholders
            $this->setMailPlaceholder('registrationdate',
                $this->wire('datetime')->date($this->getDateFormat($user), $user->created));
            $this->setMailPlaceholder('registerurl', $this->wire('pages')->get('template=fl_registerpage')->httpUrl);

            // create mail
            $m = new WireMail();
            $m->to($user->email);
            $m->from($this->loginregisterConfig['input_email']);
            $this->setSenderName($m);
            $m->subject($this->_('Your account has been deleted'));
            $m->title($this->_('Good bye!'));

            // set placeholder for title
            $this->setMailPlaceholder('title', $m->title);

            if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                $text = $this->getLangValueOfConfigField('input_deletion_confirmation', $this->loginregisterConfig,
                    $this->stored_user_lang->id);
            } else {
                $text = $this->loginregisterConfig['input_deletion_confirmation'];
            }

            $body = $this->generateEmailPreHeader($m) . $text . $this->___generateNoReplyText();
            $body = wirePopulateStringTags($body, $this->getMailPlaceholders(),
                ['tagOpen' => '[[', 'tagClose' => ']]']);

            $this->setMailPlaceholder('body', $body);
            $m->bodyHTML($body);
            $m->mailTemplate($this->loginregisterConfig['input_emailTemplate']);
            $this->includeMailTemplate($m);
            $m->mailTemplate('none'); // important to prevent double inclusion of HTML template on hooks

            if ($this->wire('modules')->isInstalled('LanguageSupport')) {
                // set back the language to the site language
                $this->user->setLanguage($this->site_language_id);
            }

            return (bool)$m->send();

        }
        return true;
    }

    /**
     * Set alert for error message if there occurs an error during the storage of a user
     * @return void
     */
    public function savingUserProblemAlert():void
    {
        $this->getAlert()->setCSSClass('alert_warningClass')->setText($this->_('A technical problem occurred during the saving of the user data, so the user data could not be saved. Please try it once more. If the problem persists please contact the webmaster of this site.'));
    }

    /**
     * Set the mail sender name to the mail
     * @param WireMail $mail
     * @return void
     * @throws WireException
     */
    protected function setSenderName(WireMail $mail):void
    {
        if ($this->loginregisterConfig['input_sender']) {
            $name = $this->loginregisterConfig['input_sender'];
        } else {
            $name = 'noreply@' . $this->wire('config')->httpHost;
        }
        $mail->fromName($name);
    }

    /**
     * Convert the values of a text box to an array
     * Each value has to be written on a new line
     * @param string|null $textarea - the value of the textarea field
     * @return array
     */
    protected function newLineToArray(string $textarea = null):array
    {
        $textarea_array = array_map('trim', explode("\n", $textarea)); // remove extra spaces from each array value
        $final_array = [];
        foreach ($textarea_array as $textarea_arr) {
            $final_array[] = trim($textarea_arr);
        }
        return $final_array;
    }

    /**
     * Method to render a link to the login page
     * @return Link
     */
    public function ___loginLink():Link
    {
        $link = new Link('login');
        $link->setPageLink($this->login_page);
        $link->setLinkText($this->_('Login?'));
        return $link;
    }

    /**
     * Redirect to homepage in user language if user is logged in
     * @param bool $guest - if it should be checked for guest status -> set to true, otherwise it checks for logged-in user
     * @return void
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function redirectToHomepage(bool $guest = false):void
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

    /** HELPER METHODS */

    /**
     * Check if query string with a specific name is present in the redirect URL
     * @param string $queryStringName - the name for the querystring parameter
     * @param string|bool $redirect - if querystring is not present redirect to this url
     * @return string - returns the sanitized query string if present, otherwise redirect
     * @throws WireException
     */
    protected function checkForQueryString(string $queryStringName, string|bool $redirect = '/'):string
    {
        // get the query string
        $queryString = $this->wire('input')->queryStringClean(['validNames' => [$queryStringName]]);
        $this->queryString = str_replace($queryStringName . '=', '', $queryString);
        if (!$this->queryString && $redirect) {
            $this->wire('session')->redirect($redirect);
        }
        return $this->queryString;
    }

    /**
     * Get the date format depending on the user language
     * @param User $user
     * @return string
     * @throws WireException
     */
    protected function getDateFormat(User $user):string
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
                // otherwise use the default format
                return $this->frontendformsConfig['input_dateformat'];
            }
        } else {
            return $this->frontendformsConfig['input_dateformat'];
        }
    }

    /**
     * Create the password field including confirmation input and old password input
     * @param string|null $type - the type of form where the field will be added (profile or register)
     * @return void
     * @throws Exception
     * @throws Exception
     */
    protected function createPass(string $type = null):void
    {
        // add password fields
        if ($type == 'input_profile') {
            // create fieldset start
            $pwfieldsetStart = new FieldsetOpen();
            $pwfieldsetStart->setLegend($this->_('Change password'));
            $this->add($pwfieldsetStart);

            // old password
            $oldPass = new InputPassword('oldpass');
            $oldPass->setLabel($this->_('Old password'));
            $oldPass->removeRule('required'); // will be changed to requiredWith afterwards
            $oldPass->setRule('requiredWith',
                $this->getID() . '-newpass')->setCustomFieldName($this->_('Old password'));
            $oldPass->setRule('checkPasswordOfUser', $this->user);
            $oldPass->showPasswordToggle();
            $this->add($oldPass);

            // new password
            $newPass = new InputPassword('pass');
            $newPass->setLabel($this->_('New password'));
            $newPass->removeRule('required'); // will be replaced by requiredWith afterwards
            $newPass->setRule('requiredWith',
                $this->getID() . '-oldpass')->setCustomFieldName($this->_('New password'));
            $newPass->showPasswordToggle();
            $newPass->showPasswordRequirements();
            $this->add($newPass);

        } else {
            // password
            $pass = new Password('pass');
            $pass->showPasswordToggle();
            $pass->showPasswordRequirements();
            $this->add($pass);
        }

        // password confirmation
        $confirm_id = $this->getID() . '-pass';
        $passConfirm = new PasswordConfirmation('pass-confirm', $confirm_id);
        $passConfirm->removeRule('required'); // will be replaced by requiredWith afterwards
        $passConfirm->setRule('requiredWith', $this->getID() . '-oldpass');
        $passConfirm->showPasswordToggle();
        $this->add($passConfirm);
        // create fieldset end
        if ($type == 'input_profile') {
            $pwfieldsetClose = new FieldsetClose();
            $this->add($pwfieldsetClose);
        }
    }

    /**
     * Create new email input field for registration and profile form
     * @return void
     * @throws WireException
     * @throws Exception
     */
    protected function createEmail():void
    {
        // add the email field
        $email = new Email('email');
        $email->setRule('uniqueEmail');
        if ($this->wire('user')->isLoggedIn()) {
            $email->setAttribute('value', $this->wire('user')->email);
        }
        $this->add($email);
    }

    /**
     * Create username field
     * @return void
     * @throws WireException
     * @throws Exception
     */
    protected function createUsername():void
    {
        // add the username field
        $username = new Username('username');
        if ($this->wire('user')->isLoggedIn()) {
            $username->setAttribute('value', $this->wire('user')->name);
        }
        $this->add($username);
    }


    /** CREATE SYSTEM FIELDS */

    /**
     * Create language select input field for changing user language
     * @return void
     * @throws WireException
     * @throws Exception
     */
    protected function createLanguage():void
    {
        if ($this->wire('modules')->isInstalled('LanguageSupport')) {
            $language = new Language('language');

            //set stored user language as default value
            $user_language = $this->getSavedUserLanguage($this->user);
            $language->setFixedLanguageID($user_language->id);
            $this->add($language);
        }
    }

    /**
     * Create Tfa input field
     * Maybe for future purposes - will not be used at the moment
     * At the moment only TfaEmail is supported
     * @return void
     * @throws WireException
     * @throws Exception
     */
    protected function createTfa():void
    {
        $tfa = new InputRadioMultiple('tfa');
        $tfa->setLabel($this->_('TFA'));
        $tfa->addOption('none', '0');
        $tfa->addOption('Email', 'TfaEmail');
        if ($this->wire('user')->isLoggedIn()) {
            $tfa->setAttribute('value', $this->wire('user')->tfa_type);
        }
        $this->add($tfa);
    }

    /**
     * Create and/or add form fields to the form
     * @param string $needle - the name of the input fields should contain this word at the end
     * @param Form $form - the form element where the fields should be added
     * @return void
     * @throws WireException
     * @throws WirePermissionException
     * @throws Exception
     */
    protected function createFormFields(string $needle, Form $form):void
    {

        $fields = $this->getFormFieldsSelected($needle);

        // These fields should not be created with the createFormField method because we create them manually
        $noCreation = ['pass', 'email', 'language', 'tfa', 'username'];

        foreach ($fields as $field) {
            if ($field->name == 'title') {
                $name = 'username';
            } else {
                $name = $field->name;
            }
            if (in_array($name, $noCreation)) {
                $methodName = 'create' . ucfirst($name);
                $this->$methodName($needle);
            } else {
                $form->add($this->createFormField($field));
            }
        }
    }

    /**
     * Get all form fields which are selected in the module configuration
     * @param string $fieldName - the name of the field in the module configuration
     * @return array - returns array of field objects
     * @throws WireException
     * @throws WirePermissionException
     */
    public function getFormFieldsSelected(string $fieldName):array
    {

        $fields = $this->loginregisterConfig[$fieldName];
        $formFields = [];
        if ($fields) {
            foreach ($fields as $fieldName) {
                $formFields[] = $this->wire('fields')->get($fieldName);
            }
        }
        return $formFields; // returns an array of field objects
    }


    /**
     * Create view of profile image
     * @param string|null $fieldname
     * @return string
     * @throws Exception
     */
    protected function createProfileImagePreview(string|null $fieldname = null):string
    {
        $string = '';

        // create outer container class
        $outer_classes = ['positioning-container'];
        if (isset($this->loginregisterConfig['input_positionclass'])) {
            $outer_classes[] = trim($this->loginregisterConfig['input_positionclass']);
        }
        $outer_classes = array_filter($outer_classes);
        $outer_classes = implode(' ', $outer_classes);

        // create img class
        $image_classes = ['profile-image'];
        if (isset($this->loginregisterConfig['input_imageclass'])) {
            $image_classes[] = trim($this->loginregisterConfig['input_imageclass']);
        }
        $image_classes = array_filter($image_classes);
        $image_classes = implode(' ', $image_classes);

        $sizes = array_map('intval', explode(',', $this->loginregisterConfig['input_image_size']));

        if ($this->user->isLoggedin()) {

            if (($fieldname) && (count($this->user->$fieldname))) {

                $userimage = $this->user->$fieldname->first();

                // only 1 size is set
                if (count($sizes) == 1) {
                    $sizes[1] = $sizes[0];
                }
                // more than 2 sizes are set
                if (count($sizes) > 2) {
                    $sizes = array_slice($sizes, 0, 2);
                }
                $thumb = $userimage->size($sizes[0], $sizes[1]);

                // crate image wrapper tag
                $string .= '<div id="' . $this->getID() . '-' . $fieldname . '-preview" class="' . $outer_classes . '" data-width="' . $sizes[0] . 'px" data-class="' . $image_classes . '">';

                // create image tag
                $string .= '<img id="' . $this->getID() . '-' . $fieldname . '-image" class="' . $image_classes . '" alt="' . sprintf($this->_('User image of %s'),
                        $this->user->name) . '" src="' . $thumb->url . '" width="' . $sizes[0] . 'px">';

                $string .= '</div>';

                // create checkbox to delete the image if an image is present
                $delete_checkbox = new InputCheckbox($this->getID() . '-' . $fieldname . '-remove');
                $delete_checkbox->setLabel($this->_('Remove this image'));
                $delete_checkbox->setAttribute('value', 'remove');
                // add JavaScript onchange attribute for the image preview to the field
                $delete_checkbox->setAttribute('onclick', 'removePreview(this);');
                $string .= $delete_checkbox->___render();
            } else {

                $string .= '<div id="' . $this->getID() . '-' . $fieldname . '-preview" class="' . $outer_classes . '" data-width="' . $sizes[0] . 'px" data-class="' . $image_classes . '"></div>';
                //$string .= '<div style="width:'.$sizes[0].'px;" id="'.$this->getID().'-' . $fieldname . '-preview" class="profile-image-wrapper"></div>';
            }
        } else {
            // user is not logged in (registration form)
            $string .= '<div id="' . $this->getID() . '-' . $fieldname . '-preview" class="' . $outer_classes . '" data-width="' . $sizes[0] . 'px" data-class="' . $image_classes . '"></div>';
        }
        return $string;
    }

    /**
     * Method to create a form field according to the settings in the backend
     * @param Field $fieldtype - a ProcessWire Fieldtype fe FieldtypePassword
     * @return InputFields|null - returns an object of the FrontendForm Class (fe InputPassword)
     * @throws WireException
     * @throws Exception
     */
    public function createFormField(Field $fieldtype):?Inputfields
    {
        $fieldtypeName = ltrim(stristr((string)$fieldtype->type, 'Fieldtype'));
        if (array_key_exists($fieldtypeName, $this->linkingFieldTypes)) {
            $class = 'FrontendForms\\' . $this->linkingFieldTypes[$fieldtypeName];
            $field = new $class($fieldtype->name);

            // check if it is profile image upload field
            if (($fieldtypeName == 'FieldtypeImage') || ($fieldtypeName == 'FieldtypeCroppableImage3')) {
                $this->image_field = $this->wire('fields')->get($field->getAttribute('name'));

                // add this field to the image fields array for later usage
                $this->image_fields[] = $field->getAttribute('name');

                // get allowed extensions from this input field and add it as rule
                if ($this->image_field->get('extensions')) {
                    $ext = explode(' ', $this->image_field->get('extensions'));
                    // add additional validator for image files
                    $field->setRule('allowedFileExt', $ext);
                }

                // remove phpini max filesize validation depending on module configuration
                if ($this->loginregisterConfig['input_phpini']) {
                    $field->removeRule('phpIniFilesize');
                }
                // add max file size validator if max file size was set in the module config
                if ($this->loginregisterConfig['input_max_filesize'] > 0) {
                    $field->setRule('allowedFileSize', $this->loginregisterConfig['input_max_filesize'] * 1024);
                }

                // add JavaScript onchange attribute for the image preview to the field
                $on_change = $field->getAttribute('onchange');
                $field->setAttribute('onchange', 'showPreview(event);' . $on_change);

                $link = $field->getClearLink();
                $onclick = $link->getAttribute('onclick');
                $link->setAttribute('onclick', 'removeImageTag(this);' . $onclick);
                $field->prepend($this->createProfileImagePreview($field->getAttribute('name')));
            }

            $field->setLabel($fieldtype->label);
            // add stored values if user is logged in
            if ($this->wire('user')->isLoggedin()) {
                $field_name = $fieldtype->name;
                $field->setAttribute('value', $this->wire('user')->$field_name);
            }
            if ($fieldtype->notes) {
                $field->setNotes($fieldtype->notes);
            }
            if ($fieldtype->description) {
                $field->setDescription($fieldtype->description);
            }
            // general rules
            if ($fieldtype->required) {
                $field->setRule('required')->setCustomFieldName($fieldtype->label);
            }
            return $field;
        }
        return null;
    }

    /**
     * Get the redirect url
     * @return string
     */
    protected function getRedirectSuccess():string
    {
        return $this->redirectSuccess;
    }

    /**
     * Redirect to a certain page after successful form submission
     * @param string $url - the url of the target
     * @return FrontendLoginRegisterPages
     */
    public function setRedirectSuccess(string $url):self
    {
        $this->redirectSuccess = trim($url);
        return $this;
    }

    /**
     * Check if a user with a certain code exists
     * @param string $codeName
     * @return boolean - true or false
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function checkForUser(string $codeName):bool
    {
        if ($this->checkQueryStringUser($codeName)) {
            return true;
        }
        $this->getAlert()->setCSSClass('alert_dangerClass')->setText(__('Sorry, but no user was found with this code.'));
        $this->showForm = false;
        return false;
    }

    /**
     * Check if a user exists with this querystring (code) in the database
     * @param string $querystringParameterName
     * @return bool
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function checkQueryStringUser(string $querystringParameterName):bool
    {
        $fieldName = str_replace('code', '', $querystringParameterName); // remove the string "code" from the string

        $user = $this->wire('users')->get('fl_' . $fieldName . '=' . $this->queryString);
        if ($user->id != 0) {
            $this->user = $user; // set the user object to the property user
            return true;
        }
        return false;
    }

}
