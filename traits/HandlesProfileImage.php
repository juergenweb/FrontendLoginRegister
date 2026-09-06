<?php

declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Trait bundling the profile image upload concern: rendering the image preview markup shown
 * next to the upload field, and saving an uploaded image from the tmp folder to the user record
 * on successful form submission.
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: HandlesProfileImage.php
 */

use Exception;
use FrontendForms\InputCheckbox;
use ProcessWire\User;
use ProcessWire\WireException;

trait HandlesProfileImage
{
    /**
     * Save an uploaded image to a user
     * @param User $user
     * @param string $form_name
     * @return void
     * @throws WireException
     */
    protected function saveProfileImage(User $user, string $form_name): void
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

                // copy just this one uploaded file from the tmp folder to the appropriate
                // assets/files folder of the user - not the whole tmp folder, since (even though
                // it is now a per-visitor folder) it could still contain a leftover file from an
                // earlier, discarded upload attempt by the same visitor
                $this->wire('files')->copy($this->tmp_profile_image_dir_path . $file_name, $dist_upload_path);

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
     * Create view of profile image
     * @param string|null $fieldname
     * @return string
     * @throws Exception
     */
    protected function createProfileImagePreview(string|null $fieldname = null): string
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
            // Use getUnformatted() instead of the magic property ($this->user->$fieldname):
            // when the image field is limited to a single file (maxFiles == 1), formatted
            // access returns a single Pageimage object instead of a Pageimages array as soon
            // as an image is uploaded, and count()/->first() below would then fail with a
            // TypeError. getUnformatted() always returns the Pageimages array, regardless of
            // the field's maxFiles setting.
            $userImages = $fieldname ? $this->user->getUnformatted($fieldname) : null;

            if (($fieldname) && ($userImages) && (count($userImages))) {
                $userimage = $userImages->first();

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
                $string .= $delete_checkbox->render();
            } else {
                $string .= '<div id="' . $this->getID() . '-' . $fieldname . '-preview" class="' . $outer_classes . '" data-width="' . $sizes[0] . 'px" data-class="' . $image_classes . '"></div>';
                //$string .= '<div style="width:'.$sizes[0].'px;" id="'.$this->getID().'-' . $fieldname . '-preview" class="profile-image-wrapper"></div>';
            }
        } else {
            // user is not logged in
            $string .= '<div id="' . $this->getID() . '-' . $fieldname . '-preview" class="' . $outer_classes . '" data-width="' . $sizes[0] . 'px" data-class="' . $image_classes . '"></div>';
        }
        return $string;
    }
}
