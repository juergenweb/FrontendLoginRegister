# Change Log
All notable changes to this project will be documented in this file.

## 23-04-26
-Fixing bug showing the wrong registration date inside activation email

## [1.0.1] 23-05-03
- Fixing errors on PW installations where Language Support module is not enabled
- Fixing problems by mails which will be send via Hook
- Setting page language as default language on new registrations 

## [1.0.2] 23-05-06
- Fixing som bugs
- Adding support for image upload. Now user image can also be uploaded on registration and profile form
- Adding various new configuration settings inside the module configuration for the profile image

## [1.0.3] 23-05-10
PLEASE UPDATE FRONTENDFORMS TO THE LATEST VERSION BEFORE UPDATE
- Adding image preview of new image before upload

## [1.2.5] 23-10-10
There were a lot of undocumented changes done in the last half year so the version number is not much higher.

In this update, the module will be prepared to support the new Ajax form submission functionality and a lot of code improvements were done too.

## [1.2.6] 23-10-29
New configuration setting for "Accept the privacy" added.

Until this version, only a checkbox to accept the privacy policy has been added to the registration form. Since this version you can select, if you want to display a checkbox, a text or nothing at all.

## [1.2.7] 23-10-30
New configuration setting to remove JS and CSS file on the frontend.

By default, a CSS and JS file for this module will be added on the frontend. If you want to disable the embedding of these files, you can do it via a new configuration setting in the backend.
A usecase for this scenario could be, that you want to copy the content of these files and include it in your own CSS and JS files.
