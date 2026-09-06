<?php

declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Trait bundling the form-field factory concern: creating the individual login/register/profile
 * form fields (password, email, username, language, TFA) and generically creating a FrontendForms
 * field from a ProcessWire Fieldtype for the dynamically configurable extra fields.
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: BuildsFormFields.php
 */

use Exception;
use FrontendForms\Email;
use FrontendForms\FieldsetClose;
use FrontendForms\FieldsetOpen;
use FrontendForms\Form;
use FrontendForms\InputFields;
use FrontendForms\InputPassword;
use FrontendForms\InputRadioMultiple;
use FrontendForms\Language;
use FrontendForms\Password;
use FrontendForms\PasswordConfirmation;
use FrontendForms\Username;
use ProcessWire\Field;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait BuildsFormFields
{
    /**
     * link the ProcessWire Fieldtype classes to the FrontendForms classes
     * This is necessary to create form fields which are set in the user template on the frontend with the
     * appropriate FrontendForms classes
     */
    protected array $linkingFieldTypes = [
        'FieldtypeEmail' => 'InputText',
        'FieldtypePassword' => 'InputPassword',
        'FieldtypeText' => 'InputText',
        'FieldtypeTextLanguage' => 'InputText',
        'FieldtypeTextarea' => 'Textarea',
        'FieldtypeTextareaLanguage' => 'Textarea',
        'FieldtypeCheckbox' => 'InputCheckbox',
        'FieldtypeInteger' => 'InputNumber',
        'FieldtypeFloat' => 'InputText',
        'FieldtypeDatetime' => 'InputDateTime',
        'FieldtypeURL' => 'InputUrl',
        'FieldtypeURLLanguage' => 'InputUrl',
        'FieldtypePage' => 'InputText',
        'FieldtypeImage' => 'FileUploadSingle',
        'FieldtypeCroppableImage3' => 'FileUploadSingle'
    ];

    /**
     * Create the password field including confirmation input and old password input
     * @param string|null $type - the type of form where the field will be added (profile or register)
     * @return void
     * @throws Exception
     */
    protected function createPass(string|null $type = null): void
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
        // create the fieldset end
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
    protected function createEmail(): void
    {
        // add the email field
        $email = new Email('email');
        $email->setRule('uniqueEmail');
        $email->setRule('required');

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
    protected function createUsername(): void
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
     * @throws WirePermissionException
     * @throws Exception
     */
    protected function createLanguage(): void
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
     * Currently only TfaEmail is supported as a value ('Email' => 'TfaEmail'); the field itself
     * is always built here, ready for additional TFA providers to add their own option later.
     * @return void
     * @throws WireException
     * @throws Exception
     */
    protected function createTfa(): void
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
    protected function createFormFields(string $needle, Form $form): void
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
    public function getFormFieldsSelected(string $fieldName): array
    {
        $fields = $this->loginregisterConfig[$fieldName];
        $formFields = [];
        if ($fields) {
            foreach ($fields as $fieldId) {
                $formFields[] = $this->wire('fields')->get($fieldId);
            }
        }
        return $formFields; // returns an array of field objects
    }

    /**
     * Method to create a form field according to the settings in the backend
     * @param Field $fieldtype - a ProcessWire Fieldtype fe FieldtypePassword
     * @return InputFields|null - returns an object of the FrontendForm Class (fe InputPassword)
     * @throws WireException
     * @throws Exception
     */
    public function createFormField(Field $fieldtype): ?Inputfields
    {
        $fieldtypeName = ltrim(stristr((string)$fieldtype->type, 'Fieldtype'));

        if (array_key_exists($fieldtypeName, $this->linkingFieldTypes)) {
            $class = 'FrontendForms\\' . $this->linkingFieldTypes[$fieldtypeName];
            $field = new $class($fieldtype->name);

            // check if it is a profile image upload field
            if (($fieldtypeName == 'FieldtypeImage') || ($fieldtypeName == 'FieldtypeCroppableImage3')) {
                $this->image_field = $this->wire('fields')->get($field->getAttribute('name'));

                // add this field to the image fields array for later usage
                $this->image_fields[] = $field->getAttribute('name');

                // get allowed extensions from this input field and add it as a rule
                if ($this->image_field->get('extensions')) {
                    $ext = explode(' ', $this->image_field->get('extensions'));
                    // add additional validator for image files
                    $field->setRule('allowedFileExt', $ext);
                }

                // remove phpini max filesize validation depending on module configuration
                if ($this->loginregisterConfig['input_phpini']) {
                    $field->removeRule('phpIniUploadMaxFileSize');
                }
                // add max file size validator if max file size was set in the module config
                if ($this->loginregisterConfig['input_max_filesize'] > 0) {
                    $field->setRule('maxSingleFileSize', $this->loginregisterConfig['input_max_filesize'] * 1024);
                }

                // add JavaScript onchange attribute for the image preview to the field
                $on_change = $field->getAttribute('onchange');
                $field->setAttribute('onchange', 'showPreview(event);' . $on_change);
                $preview = $this->createProfileImagePreview($field->getAttribute('name'));
                // Prepend to the field's outer wrapper, not the field itself: $field->prepend()
                // would insert the preview markup (image thumbnail + "remove image" checkbox)
                // inside the file input's own .uk-form-custom wrapper, where UIkit3 stretches
                // the (invisible) real <input type="file"> over its entire parent to make the
                // whole area clickable - covering the preview image and checkbox as a result.
                $field->getFieldWrapper()->prepend($preview);
            }

            $field->setLabel($fieldtype->label);
            // add stored values if a user is logged in
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
}
