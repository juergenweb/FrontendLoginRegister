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


class FrontendLoginRegisterPages extends Form
{

    use activation;

    /*properties*/
    protected string $redirectSuccess = '';
    protected string $queryString = ''; // The get parameter of the querystring
    protected string $input_preventIPs = ''; // String of forbidden IP addresses

    protected array $loginregisterConfig = []; // array that holds all module configuration properties
    protected array $frontendformsConfig = []; // array that holds all module configuration properties from FrontenForms
    /*objects*/
    protected Page $login_page; // the login page object
    protected Page $delete_page; // the delete page object
    protected Page $delete_request_page; // the delete request page object
    protected FrontendForms $frontendForms; // the FrontendForms module object

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
            $this->loginregisterConfig[$key] = $value;
        }

        // grab FrontendForms object to get configuration values
        $this->frontendForms = $this->wire('modules')->get('Frontendforms');
        foreach ($this->wire('modules')->getConfig('FrontendForms') as $key => $value) {
            $this->frontendformsConfig[$key] = $value;
        }

        $this->login_page = $this->wire('pages')->get('template=fl_loginpage');
        $this->delete_page = $this->wire('pages')->get('template=fl_deleteaccountpage');
        $this->delete_request_page = $this->wire('pages')->get('template=fl_deleterequestpage');


    }

    /**
     * Set all user data entered inside a form to a user object
     * Will be saved later on to the user
     * @param User $user
     * @return void
     */
    protected function setFormFieldValues(User $user):void
    {
        foreach($this->formElements as $field){
            // exclude password-confirm field if present
            $exclude_fields = [
                $this->getID().'-pass-confirm',
                $this->getID().'-oldpass'
            ];
            if(($field->getAttribute('name')) && (!in_array($field->getAttribute('name'), $exclude_fields))){
                $field_name = $field->getAttribute('name');
                $cleaned_field_name = str_replace($this->getID().'-', '', $field_name);
                // username has to be changed to name as stored inside the database
                if($cleaned_field_name == 'username'){
                    $cleaned_field_name == 'name';
                }
                $user->$cleaned_field_name = $this->getValue($field_name);
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
        'FieldtypePage' => 'InputText'
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
     * Send a reminder mail to the user if account is not activated
     * @param User $user
     * @return bool
     * @throws WireException
     */
    protected function sendReminderMail(User $user):bool
    {

        $days_to_delete = $this->daysToDelete($user);

        $date_to_delete_ts = (new DateTime('NOW'))->modify('+' . $days_to_delete . ' days')->getTimestamp();

        $m = new WireMail();

        // create placeholder variables
        // 1) registration date
        $this->setMailPlaceholder('registrationdate',
            $this->wire('datetime')->date($this->getDateFormat($user), $user->created));
        // 2) number of days to deletion
        // nicht aktiviert
        $daystodelete_text = $this->_n('day', 'days', $days_to_delete);
        $this->setMailPlaceholder('daystodelete', $days_to_delete . ' ' . $daystodelete_text);
        // 3) deletion date
        $this->setMailPlaceholder('deletedate',
            $this->wire('datetime')->date($this->getDateFormat($user), $date_to_delete_ts));
        // 4) verification link
        $this->setMailPlaceholder('verificationlink', $this->createActivationLink($user));
        // 5) not registered link
        $this->setMailPlaceholder('notregisteredlink', $this->createNotRegisteredLink($user));

        $m->to($user->email);
        $m->from($this->loginregisterConfig['input_email']);
        $this->setSenderName($m);
        $m->subject($this->_('Action required to activate your account'));
        $m->title($this->_('Have you forgotten to verify your account?'));
        $m->bodyHTML($this->getLangValueOfConfigField('input_remindertext', $this->loginregisterConfig));
        $m->mailTemplate($this->loginregisterConfig['input_emailTemplate']);

        if ($m->send()) {
            return true;
        }
        return false;
    }


    /**
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function sendDeletionConfirmationMail(User $user):bool
    {
        if (!$this->loginregisterConfig['input_prevent_send_deletion_email']) {

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
            $m->bodyHTML($this->getLangValueOfConfigField('input_deletion_confirmation', $this->loginregisterConfig));
            $m->mailTemplate($this->loginregisterConfig['input_emailTemplate']);
            if ($m->send()) {
                return true;
            }
            return false;
        }
        return true;
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
     * @param string $redirect - if querystring is not present redirect to this url
     * @return string - returns the sanitized query string if present, otherwise redirect
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function checkForQueryString(string $queryStringName, string|bool $redirect = '/'):string
    {
        // get the query string
        $queryString = $this->wire('input')->queryStringClean(['validNames' => [$queryStringName]]);
        $this->queryString = str_replace($queryStringName . '=', '', $queryString);
        //$this->queryString = $this->wire('sanitizer')->entities($this->wire('input')->get($queryStringName));
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
            if (property_exists($this, $date_property)) {
                // check if a date format in the given language exists
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
        $language = new Language('language');

        // add language field only if number of languages is higher than 1
        if (count($this->wire('languages')) > 1) {
            $this->add($language);
        }
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

        // These fields should not be created with the createFormField method because we create them manually
        $noCreation = ['pass', 'email', 'language', 'tfa'];

        // add username field on top
        if ($this->loginregisterConfig['input_selectlogin'] == 'username') {
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

}
