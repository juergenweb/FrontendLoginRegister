<?php

declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Class for creating showing a profile form in the members' area
 * Only for logged-in users to update their data
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: Profile.php
 * Created: 06.07.2022
 */

use Exception;
use FrontendForms\Button as Button;
use FrontendForms\Link as Link;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

class ProfilePage extends FrontendLoginRegisterPages
{
    // objects
    protected Link $deleteAccountLink; // link object for the delete link

    /**
     * Every form must have an ID, so let's add it via the constructor
     * @param string $id
     * @throws WireException
     * @throws WirePermissionException
     * @throws Exception
     */
    public function __construct(string $id = 'profile-form')
    {
        parent::__construct($id);

        // if user is guest -> no need to be here -> let's redirect to the homepage
        $this->redirectToHomepage(true);

        // create instance of the deletion link
        $this->deleteAccountLink = new Link('deleteAccount');

        // default settings
        $this->setMinTime(3);
        $this->setMaxTime(3600);
        $this->useDoubleFormSubmissionCheck(false); // allow multiple submissions to change profile data
        $this->disableCaptcha(); // disable Captcha
        $this->setSubmitWithAjax($this->useAjax);

        // create all form fields depending on the settings in the backend
        $this->createFormFields('input_profile', $this);

        // Show or hide the display of the toggle checkbox next to password fields.
        // Guarded: unlike the registration form, including the password field in the profile
        // form is only recommended, not enforced (see FrontendLoginRegister::validateFormFields()
        // - a missing password field only triggers a warning there, not a blocking error), so
        // these fields may not exist and getFormelementByName() can return false.
        $passField = $this->getFormelementByName('pass');
        if ($passField) {
            $passField->showPasswordToggle(!$this->loginregisterConfig['input_hide_passwordtoggle']);
        }
        $passConfirmField = $this->getFormelementByName('pass-confirm');
        if ($passConfirmField) {
            $passConfirmField->showPasswordToggle(!$this->loginregisterConfig['input_hide_passwordtoggle']);
        }
        $oldPassField = $this->getFormelementByName('oldpass');
        if ($oldPassField) {
            $oldPassField->showPasswordToggle(!$this->loginregisterConfig['input_hide_passwordtoggle'])
                ->showPasswordRequirements(false);
        }

        // create and add the submission button to the form
        $button = new Button('submit');
        $button->setAttribute('value', $this->_('Update'));
        $this->add($button);

        // set language values on redirect if language field is present inside the form
        $language_field = $this->getFormelementByName('language');
        if ($language_field) {
            if ($this->wire('session')->get('language')) {
                $language_field->setDefaultValue($this->wire('session')->get('language'));
                $this->wire('session')->remove('language');
            } else {
                // needed to display the correct language value in lang select after refresh
                $language_field->setDefaultValue((string)$this->user->language->id);
            }
        }

        $this->setUploadPath($this->tmp_profile_image_dir_path);
    }

    /**
     * Method to create a link to delete the account
     * @return Link
     * @throws WireException
     */
    public function ___deleteAccountLink(): Link
    {
        $this->deleteAccountLink->setPageLink($this->wire('pages')->get('template=fl_deleterequestpage'))->setLinkText($this->_('Delete account'));
        $this->deleteAccountLink->wrap();
        return $this->deleteAccountLink;
    }

    /**
     * Return the rendered form markup
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     * @throws Exception
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Render the form and save the user data
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     * @throws Exception
     */
    public function render(): string
    {
        $content = '';
        if (!$this->getSubmitWithAjax()) {
            $content .= $this->prependBody();
        }
        // Show the link for account deletion, if enabled in the settings
        if ($this->loginregisterConfig['input_deleteProfile']) {
            $this->add($this->deleteAccountLink());
        }

        if ($this->isValid()) {
            // grab the user language id before the saving process on multi-language site
            if ($this->wire('languages') && count($this->wire('languages')) > 1) {
                $old_user_lang = $this->user->language->id;
            }

            // save an uploaded profile image to the given user
            $this->saveProfileImage($this->user, 'profile-form');

            // set the form field values to the user object
            $this->setFormFieldValues($this->user);

            if ($this->user->save()) {
                $this->wire('session')->set('valid', '1');
                // check if the site is multilingual
                if ($this->wire('languages') && count($this->wire('languages')) > 1) {
                    if ($old_user_lang != $this->user->language->id) {
                        $this->wire('session')->set('language', (string)$this->user->language->id);
                        // redirect to the local page with the new language
                        $this->wire('session')->redirect($this->wire('pages')->get($this->wire->page->id)->localUrl($this->wire('user')->language) . $this->segments);
                    }
                }
                // redirect to the page -> necessary to show new uploaded image
                $this->wire('session')->redirect($this->wire('page')->url . $this->segments);
            } else {
                $this->savingUserProblemAlert();
            }
            $this->user->of(true); // set user to true again

            // clear all password fields (guarded - see the constructor for why these fields can be absent)
            $oldPassField = $this->getFormelementByName('oldpass');
            if ($oldPassField) {
                $oldPassField->setAttribute('value', '');
            }
            $passField = $this->getFormelementByName('pass');
            if ($passField) {
                $passField->setAttribute('value', '');
            }
            $passConfirmField = $this->getFormelementByName('pass-confirm');
            if ($passConfirmField) {
                $passConfirmField->setAttribute('value', '');
            }
        } else {
            if ($this->getSubmitWithAjax()) {
                $content .= $this->prependBody();
            }
        }

        if ($this->wire('session')->get('valid')) {
            $this->setAlert('alert_successClass', $this->_('Your profile data have been updated successfully.'));
            $this->wire('session')->remove('valid');
        }

        // render the form on the frontend
        return $content . parent::render();
    }
}
