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

use Exception;
use FrontendForms\Email as Email;
use FrontendForms\FieldsetClose as FieldsetClose;
use FrontendForms\FieldsetOpen as FieldsetOpen;
use FrontendForms\Form as Form;
use FrontendForms\InputFields;
use FrontendForms\InputPassword as InputPassword;
use FrontendForms\InputRadioMultiple as InputRadioMultiple;
use FrontendForms\Password as Password;
use FrontendForms\PasswordConfirmation as PasswordConfirmation;
use FrontendForms\Select as Select;
use FrontendForms\Username as Username;
use ProcessWire\Field;
use ProcessWire\FrontendForms;
use ProcessWire\Page;
use ProcessWire\User;
use ProcessWire\WireException;
use ProcessWire\WireMail;
use ProcessWire\WirePermissionException;
use FrontendForms\Link as Link;


class FrontendLoginRegisterPages extends Form
{

    use activation;

    /*properties*/
    protected string $redirectSuccess = '';
    protected string $queryString = ''; // The get parameter of the querystring
    protected string $input_preventIPs = ''; // String of forbidden IP addresses
    protected string $pathToBodyFolder = ''; // The path to the mail body folder

    /*objects*/
    protected Page $login_page; // the login page object
    protected Page $delete_page; // the delete page object
    protected Page $delete_request_page; // the delete request page object
    protected FrontendForms $frontendForms;

    /**
     * Every form must have an id, so let's add it via the constructor
     * @param string $id
     * @throws WireException
     */
    public function __construct(string $id)
    {
        parent::__construct($id);

        // get module configuration data from FrontendLoginRegister module and create properties of each setting
        foreach ($this->wire('modules')->getConfig('FrontendLoginRegister') as $key => $value) {
            $this->$key = $value;
        }

        $this->login_page = $this->wire('pages')->get('template=fl_loginpage');
        $this->delete_page = $this->wire('pages')->get('template=fl_deleteaccountpage');
        $this->delete_request_page = $this->wire('pages')->get('template=fl_deleterequestpage');

        // grab FrontendForms object to get configuration values
        $this->frontendForms = $this->wire('modules')->get('Frontendforms');

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
        'FieldtypePage' => 'InputText'
    ];

    /**
     * Send a reminder mail to the user if account is not activated
     * @param User $user
     * @return bool
     * @throws WireException
     */
    protected function sendReminderMail(User $user): bool
    {
        $m = new WireMail();
        // create placeholder variables
        // 1) registration date
        $this->setMailPlaceholder('registrationdate',
            $this->wire('datetime')->date($this->getDateFormat($user), $user->created));
        // 2) number of days to deletion
        $daystodelete = $this->_n('day', 'days', $this->input_delete);
        $this->setMailPlaceholder('daystodelete', (string)$this->input_delete.' '.$daystodelete);
        // 3) deletion date
        //calculate delete date
        $delete_date_ts = time() + ((int)$this->input_delete * 86400);
        $this->setMailPlaceholder('deletedate',
            $this->wire('datetime')->date($this->getDateFormat($user), $delete_date_ts));
        // 4) verification link
        $this->setMailPlaceholder('verificationlink', $this->createActivationLink($user));

        $m->to($user->email);
        $m->from($this->input_email);
        $this->setSenderName($m);
        $m->subject($this->_('Action required to activate your account'));
        $m->title($this->_('Have you forgotten to verify your account?'));
        $m->bodyHTML($this->getLangValueOfConfigField('input_remindertext'));
        $m->mailTemplate($this->input_emailTemplate);
        if ($m->send()) {
            return true;
        }
        return false;
    }


    /**
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function sendDeletionConfirmationMail(User $user): bool
    {
        $m = new WireMail();
        // create placeholder variables

        $days  = $this->input_delete + $this->input_remind;
        $this->setMailPlaceholder('daystodelete', (string)$days);

        $this->setMailPlaceholder('registrationdate',
            $this->wire('datetime')->date($this->getDateFormat($user), $user->created));
        $this->setMailPlaceholder('registerurl', $this->wire('pages')->get('template=fl_registerpage')->httpUrl);

        $m->to($user->email);
        $m->from($this->input_email);
        $this->setSenderName($m);
        $m->subject($this->_('Your account has been deleted'));
        $m->title($this->_('Good bye!'));
        $m->bodyHTML($this->getLangValueOfConfigField('input_deletion_confirmation'));
        $m->mailTemplate($this->input_emailTemplate);
        if ($m->send()) {
            return true;
        }
        return false;
    }


    /**
     * Set alert for error message if there occurs an error during the storage of a user
     */
    public function savingUserProblemAlert()
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
        if ($this->input_sender) {
            $name = $this->input_sender;
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
     * @param string $redirect - if querystring is not present redirect to this url
     * @return string - returns the sanitized query string if present, otherwise redirect
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function checkForQueryString(string $queryStringName, string $redirect = '/'):string
    {
        // get the query string
        $queryString = $this->wire('input')->queryStringClean(['validNames' => [$queryStringName]]);
        $this->queryString = str_replace($queryStringName . '=', '', $queryString);
        //$this->queryString = $this->wire('sanitizer')->entities($this->wire('input')->get($queryStringName));
        if (!$this->queryString) {
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
            if (property_exists($this, $date_property)) {
                // check if a date format in the given language exists
                return $this->$date_property;
            } else {
                // otherwise use the default format
                return $this->input_dateformat;
            }
        } else {
            return $this->input_dateformat;
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
            $oldPass->setRule('requiredWith', 'newpass')->setCustomFieldName($this->_('Old password'));
            $oldPass->setRule('checkPasswordOfUser', $this->user);
            $oldPass->showPasswordToggle();
            $this->add($oldPass);
            // new password
            $newPass = new InputPassword('pass');
            $newPass->setLabel($this->_('New password'));
            $newPass->setRule('requiredWith', 'oldpass')->setCustomFieldName($this->_('New password'));
            $newPass->setRule('meetsPasswordConditions');
            $newPass->setRule('safePassword');
            $newPass->showPasswordToggle();
            $newPass->showPasswordRequirements();
            $this->add($newPass);
        } else {
            // password
            $pass = new Password('pass');
            $pass->setRule('meetsPasswordConditions');
            $pass->setRule('safePassword');
            $pass->showPasswordToggle();
            $pass->showPasswordRequirements();
            $this->add($pass);
        }

        // password confirmation
        $confirm_id = $this->getID() . '-pass';
        $passConfirm = new PasswordConfirmation($confirm_id, 'pass-confirm');
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

    /** CREATE SYSTEM FIELDS */

    /**
     * Create language select input field for changing user language
     * @return void
     * @throws WireException
     */
    protected function createLanguage():void
    {
        // add the language field
        $language = new Select('language');
        $language->setLabel($this->_('Language'));
        $languageIDs = [];
        $user_lang_id = $this->user->language->id;

        $language->setAttribute('value', $user_lang_id);
        foreach ($this->wire('languages') as $lang) {
            $languageIDs[] = $lang->id;
        }

        $language->setRule('required')->setCustomFieldName($this->_('Language'));
        $language->setRule('integer');
        $language->setRule('In', $languageIDs);
        if (count($languageIDs) > 1) {
            $this->add($language);
        } // show this input only if site is multi-language (number of languages > 1)
    }

    /**
     * Create Tfa input field
     * @return void
     * @throws WireException
     * @throws Exception
     */
    protected function createTfa():void
    {
        //_pw_page_name
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
     */
    protected function createFormFields(string $needle, Form $form):void
    {

        $fields = $this->getFormFieldsSelected($needle);

        // sort the fields new, that the password field is always on the second position
        foreach ($fields as $key => $val) {
            if ($val['name'] === 'pass') {
                $field_key = $key;
            }
        }
        // get the password field object
        $pass_field = $fields[$field_key];

        // remove the pass field from the array
        unset($fields[$field_key]);

        // insert it on the second position [1] after username or email
        $fields[] = $pass_field;

        $noCreation = ['pass', 'email', 'language', 'tfa'];
        // These fields should not be created with the createFormField method because we create them manually

        // add username field on top
        if ($this->input_selectlogin == 'username') {
            $form->add($this->createName());
        }

        foreach ($fields as $field) {
            if (in_array($field->name, $noCreation)) {
                $methodName = 'create' . ucfirst($field->name);
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
        $fields = $this->$fieldName;
        $formFields = [];
        if ($fields) {
            foreach ($fields as $fieldName) {
                $formFields[] = $this->wire('fields')->get($fieldName);
            }
        }
        return $formFields; // returns an array of field objects
    }

    /**
     * Create input field for the usernameSyntax
     * @return Username
     */
    protected function createName():Username
    {
        // add the field
        $name = new Username('username');
        $name->setRule('uniqueUsername');
        return $name;
    }

    /**
     * Method to create a form field according to the settings in the backend
     * @param Field $fieldtype - a ProcessWire Fieldtype fe FieldtypePassword
     * @return InputFields|null - returns an object of the FrontendForm Class (fe InputPassword)
     */
    public function createFormField(Field $fieldtype):?Inputfields
    {
        $fieldtypeName = ltrim(stristr((string)$fieldtype->type, 'Fieldtype'));
        if (array_key_exists($fieldtypeName, $this->linkingFieldTypes)) {
            $class = 'FrontendForms\\' . $this->linkingFieldTypes[$fieldtypeName];
            $field = new $class($fieldtype->name);
            $field->setLabel($fieldtype->label);
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

}