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

use FrontendForms\Button as Button;
use FrontendForms\Link as Link;
use ProcessWire\WireException;

class ProfilePage extends FrontendLoginRegisterPages
{

    // properties

    // objects
    protected Link $deleteAccountLink;  // link object for the delete link

    /**
     * Every form must have an id, so let's add it via the constructor
     * @throws WireException
     * @throws WireException
     */
    public function __construct(string $id = 'profile-form')
    {
        parent::__construct($id);

        // if user is guest -> no need to be here -> lets redirect to the homepage
        $this->redirectToHomepage(true);

        $success_msg = $this->_('Your profile data have been updated successfully.');

        // create instance of deletion link
        $this->deleteAccountLink = new Link('deleteAccount');

        // default settings
        $this->setMinTime(3);
        $this->setMaxTime(3600);
        $this->useDoubleFormSubmissionCheck(false); // allow multiple submissions to change profile data
        $this->disableCaptcha(); // disable Captcha

        // create all form fields depending on the settings in the backend
        $this->createFormFields('input_profile', $this);

        // create and add the submit button to the form
        $button = new Button('submit');
        $button->setAttribute('value', $this->_('Update'));
        $this->add($button);

        // set success message
        if($this->wire('input')->post('profile-form-submit')) {
            // set success message by default on post
            $this->setSuccessMsg($success_msg);
        }

        // set language values on redirect if language field is present inside the form
        $language_field = $this->getFormelementByName('profile-form-language');
        if($language_field){
            if($this->wire('session')->get('language')){
                // display the success message after redirection
                $this->setSuccessMsg($this->_('Your profile data have been updated successfully.'));
                $language_field->setDefaultValue($this->wire('session')->get('language'));
                $this->getAlert()->setText($success_msg);
                $this->wire('session')->remove('language');
            } else {
                // needed to display the correct language value in lang select after refresh
                $language_field->setDefaultValue((string)$this->user->language->id);
            }
        }

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
     * @return string
     * @throws WireException
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * Render the form and save the user data
     * @return string
     * @throws WireException
     */
    public function render(): string
    {

        // Show link for account deletion, if enabled in the settings
        if ($this->input_deleteProfile) {
            $this->add($this->___deleteAccountLink());
        }

        if ($this->isValid()) {

            $this->showForm = true;

            // grab the user language id before the saving process on multi-language site
            if($this->wire('languages') && count($this->wire('languages')) > 1) {
                $old_user_lang = $this->user->language->id;
            }

            // save the user values to the database
            $this->user->of(false);
            foreach ($this->getValues() as $name => $value) {

                $name = str_replace($this->getID() . '-', '', $name);
                if ($this->user->hasField($name)) {
                    if ($name == 'pass') {
                        if ($value) {
                            $this->user->pass = $value;
                        }
                    }  else {

                        $this->user->$name = $value;
                    }
                } else {
                    // username has to be changed to name as stored inside the database
                    if($name == 'username'){
                        $this->user->name = $value;
                    }
                }
            }
            if($this->user->save()){
                // check if site is multi-lingual
                if($this->wire('languages') && count($this->wire('languages')) > 1) {
                    if($old_user_lang != $this->user->language->id){
                        $this->wire('session')->set('language', (string)$this->user->language->id);
                        // redirect to the local page with the new language
                        $this->wire('session')->redirect($this->wire('pages')->get($this->wire->page->id)->localUrl($this->wire('user')->language));
                    }
                }
            } else {
                $this->savingUserProblemAlert();
            }
            $this->user->of(true); // set user to true again
            // clear all password fields
            $this->getFormelementByName('profile-form-oldpass')->setAttribute('value', '');
            $this->getFormelementByName('profile-form-pass')->setAttribute('value', '');
            $this->getFormelementByName('profile-form-pass-confirm')->setAttribute('value', '');
        }

        return parent::render();
    }

}