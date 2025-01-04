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

## [1.2.8] 23-10-31
Minor bug fixes, a new method setPrependBody() added and new configuration settings added

This is the finale version before the module will be added to the module directory of ProcessWire. I have optimized some code and text and I have added the new method setPrependBody(), which allows you to enable/disable the visibility of the body text after successful form submission.
The last addition is a new configuration setting, which let you disable the toggle checkbox next to each password fields. This toggle checkbox let you display entered passwords in plain text. With this new feature, you can remove this checkbox from each password field inside this module.

## [1.2.9] 23-12-28
Due to problems of the static method secondsToReadable() in certain IDEs, I have switched to a non-static method. This method is originally from the FrontendForms module. I have copied this function to this module. Be aware to update this module first (or contemporaneously with FrontendForms) before you update FrontendForms. Otherwise you will run into problems if you are using TfaEmail 2-factor authentification and have not updated this module, but you are running the latest FrontendForms the version.

## [1.3.0] 24-01-18
Support for [Postmark mail service](https://postmarkapp.com/) added.

Due to a user request by mayks in the support forum (https://processwire.com/talk/topic/28352-frontendloginregister-a-configurable-module-for-fast-implementation-of-an-user-management-system-based-on-frontendforms/?do=findComment&comment=238466), where he asked about to add support for sending mails with Postmark, I have added this feature now.

There are 2 ProcessWire modules in the modules directory which implement the Postmark service in Processwire:

- [WireMailPostmark](https://processwire.com/modules/wire-mail-postmark/) by Netcarver
- [WireMailPostmarkApp](https://processwire.com/modules/wire-mail-postmark-app/) by NB Communication

Both modules do pretty the same, only the module configuration is slightly different.

If you have installed one of them, you will be able to send mails from this module via the Postmark service.

I have added a new field to the module configuration which let you select, if you want to send the mails of this module via the Postmark service or not.

Please note: This new field is only visible if you have installed one of the modules mentionend above. If not, you will not see this new field.

I have planned to make this module working with other 3rd party mail service modules too, but for now I have only implemented and tested Postmark.

You have to update FrontendForms to at least version 2.1.57 to be able to update this module, because most of the code changes are done in FrontendForms. The reason for that is, that this addition should be usable in other modules based on FrontendForms too and therefore placing the code directly in FrontendForms was the best way to make this feature applicable in any child module as well.

## [1.3.1] 24-01-19

As the next step, support for an additional mailing module added: [WireMailSmtp](https://processwire.com/modules/wire-mail-smtp/) by Horst.

## [1.3.2] 24-01-21

Support for sending mails with [WireMailPHPMailer](https://processwire.com/modules/altivebirit/) added.

## [1.3.3] 24-07-13

Some bugs fixed for inputfield dependencies usage.

## [1.3.4] 24-07-13

Wrong Ajax settings check fixed on various pages of the module.

## [1.3.5] 2024-12-18

Support for usage of url segements on profile page added.

- **Image picker select added**

The default input select field for selecting the email template has been replaced by a nice image picker select like in FrontendForms.

- **Support for RockLanguage added**

If you have installed the [RockLanguage](https://processwire.com/modules/rock-language/) module by Bernhard Baumrock, this module now supports the sync of the language files. This means that you do not have to take care about new translations after you have downloaded a new version of FrontendLoginRegister. All new translations (at the moment only German translations) will be synced with your your ProcessWire language files. 

Please note: The sync will only take place if you are logged in as Superuser and $config->debug is set to true (take a look at the [docs](https://www.baumrock.com/en/processwire/modules/rocklanguage/docs/)).

The (old) CSV files usage is still supported.

## [1.3.6] 2025-01-04

- **Support for making account deletion page public viewable added**

Due to a user request, a new configuration field (checkbox) to make the delete request page for a user account public viewable if needed. In the past this page was only viewable if the user was logged in.

- **Support for multilanguage fields in user template added**

The following multilanguage fields, which can be used in the user template will be supported now:

* FieldtypeTextLanguage
* FieldtypeTextareaLanguage
* FieldtypeUrlLanguage

These fieldtypes were not supported in the past and the usage of them inside the user template led to an error. This should be fixed now after this update.
