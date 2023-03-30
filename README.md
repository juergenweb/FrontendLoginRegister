# FrontendLoginRegister
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![ProcessWire 3](https://img.shields.io/badge/ProcessWire-3.x-orange.svg)](https://github.com/processwire/processwire)

A module for ProcessWire CMS to integrate a user registration/login functionality based on the FrontendForms module.
This module creates pages and templates during the installation for faster development.

> ⚠ This module is very Alpha stage - so be aware of using it on live sites!


## Requirements
- ProcessWire 3.0.195 or newer
- PHP 8.0 or newer
- FrontendForms 2.1.25 or newer: Please download and install it from https://github.com/juergenweb/FrontendForms first.
- CronJob: Will be installed automatically, if not installed
- TfaEmail: Only if you want to use 2-factor-authentication with email and code

## Highlights
- "One-click" integration of an user login/registration system without the hazzle of creating all pages and forms by hand
- "One-click" switch between login or login and registration option
- Double opt-in with activation link on new registrations
- Option for automatic sending of reminder mails, if account activation is still pending
- Option for automatic deletion of unverified accounts after a certain time to prevent unused accounts
- Option to use TFA-Email if installed for higher security on login
- Mutli-language
- Select if you want to login with username and password or email and password
- Select the desired roles for newly created users
- Select, which fields of the user template should be displayed on the registration and profile form (beside the mandatory fields). Fields and order can be changed via drag and drop functionality
- Offer users the possibility to delete their account in the members area using a deletion link with time expiration
- Customize the texts of the emails which will be send by this module
- Usage of all the benefits of [FrontendForms](https://github.com/juergenweb/FrontendForms#highlights) (fe. CAPTCHA, various security settings,...)
- Support for SeoMaestro if installed
- Lock accounts if suspicious login attempts were made

## Table of contents

- [Installation](https://github.com/juergenweb/FrontendLoginRegister/blob/main/README.md#installation)
- [Configuration](https://github.com/juergenweb/FrontendLoginRegister/blob/main/README.md#configuration)
- [What happens during the installation process?](https://github.com/juergenweb/FrontendLoginRegister/blob/main/README.md#what-happens-during-the-installation-process)
- [Sending emails](https://github.com/juergenweb/FrontendLoginRegister/blob/main/README.md#sending-emails)
- [Customizing email texts](https://github.com/juergenweb/FrontendLoginRegister/blob/main/README.md#customizing-the-email-texts)
- [Security features](https://github.com/juergenweb/FrontendLoginRegister/blob/main/README.md#security-features)
- [Support for custom user fields](https://github.com/juergenweb/FrontendLoginRegister/blob/main/README.md#support-for-custom-user-fields)
- [Multi-language](https://github.com/juergenweb/FrontendLoginRegister/blob/main/README.md#multi-language)

## Installation
Download the module, rename the module folder from FrontendLoginRegister-main to FrontendLoginRegister and add it inside the
site/modules folder. Refresh your modules in the backend and install the module as usual.
Go to the module configuration and make your settings.

## Configuration
The FrontendLoginRegister module works out of the box, but it offers the following configuration:

* **`Login or Login/Registration`** Select, if you want to offer only login or in addition the possibility for registration
* **`Userdata for login`** Decide whether to log in with username and password or email and password
* **`Redirect after login`** You can select a page, where the user should be redirected after successful login
* **`TFA`** You can enable 2-factor-authentication with email (TfaEmail) if installed.
* **`Sending reminder email`** Set the time in days after which a user should be reminded to activate his account if not
  verified
* **`Delete unverified accounts`** Set the time in days after which an unverified account should be deleted
* **`Set roles`** Select which role(s) should be added to a newly created user
* **`Set form fields for registration form`** Select which fields of the user template should be displayed inside
  registration form
* **`Set form fields for profile form`** Select which fields of the user template should be displayed inside
  profile form (page for user data if the user is logged in)
* **`Offer account deletion`** Select if you want to enable account deletion for users, so that the user can delete his
  account by himself
* **`Select email template`** Select which HTML email template should be used for sending emails
* **`From email address`** Enter the email address, which should be displayed as sender email address for emails
* **`Sender name`** Enter the name, which should be displayed as sender of the emails
* **`Email Texts`** You can customize the text of each email, that will be send by this module, using a CKEditor field

Some settings will be taken from the FrontendForms module settings (fe enable/disable Captcha, logging failed attempts,...)
and cannot be set individually in the module configuration of this module globally, but you can always change form
parameters on each template by using the FrontendForms API.

## Changing parameters of each form via API inside the template
During the installation of this module a template for each page will be created and this will look like
this:

```php
// This is the code of the fl_registerpage.php which includes the registration form
            echo '<div id="content">';
            echo $modules->get('FrontendLoginRegister')->RegisterPage();
            echo '</div>';
```
If you want to change some parameters of a form, you can use the FrontendForms API

```php
// This is the code of the fl_registerpage.php which includes the registration form
            echo '<div id="content">';
            $form = $modules->get('FrontendLoginRegister')->RegisterPage();
            $form->setErrorMsg('OMG, there are errors!'); // set a new error message
            $form->disableCaptcha(); // disable the CAPTCHA if it is globally enabled
            $form->setMinTime(10); // set the min time until the form should be submitted to 10 seconds
            echo $form->render();
            echo '</div>';
```

You can find much more infos in the Readme file of the FrontendForms module, so I do not want to go more
into detail at this point.

If you uninstall the module, all template files including your changes will be copied to a backup folder inside the
root folder of this module.

On a reinstall this backup-files with all of your changes will be taken and copied
to the templates folder. The backup folder will be deleted afterwards.

So all your changes will not get lost after uninstall of the module. Only if you delete the module completely all your 
changes will be deleted too.

## What happens during the installation process?

### Creation of 8 new user fields which will be added to the user template

During the installation process of this module 8 new fields will be created:

- fl_activation: Stores the activation code if a new account was created
- fl_activationdatetime: Holds the date and time when the user has verified his account
- fl_reminderDateTime: Holds the date and time when the reminder email was sent
- fl_recoverylogindata: Stores the recovery code if a new password was requested
- fl_recoverylogindatadatetime: Stores the date and time when the recovery code was created
- fl_deleteaccount: This field holds the deletion code if the user wants to delete his account
- fl_deleteaccountdatetime: This field holds the date/time when the deletion code was created. This is needed because the deletion code is only valid for 5 minutes.
- fl_unlockaccount: This field contains the code to unlock the account

These 8 fields will be added to the user template, but they are hidden by default, because they are only needed for
the administration of users by this module and not to be filled out by humans.
These fields fe contain activation codes, timestamps and so on... nothing you will need to take care about.

### Creation of 10 new templates

In addition 10 new templates will be created. These templates are needed for the pages, that will be created later on by this
module.

*Please note*: You do not have to use the pages/templates for login and registration form created by this module to keep the module working. You can also copy the code of these 2 templates to another template - so it is up to you. All other templates/pages should remain untouched, because the links inside the emails point to these pages!

> ⚠ But take care that some settings do not take effect when you use your own pages for registration and login. Fe. if you switch from login and registration to login only in the backend and you do not use the built in template for the registration form, it will not have an effect, because this configuration setting takes care of the pages created by this module and not on your custom pages. So the registration form will be still visible in this case.

Creation of 10 templates (one for each page) seems to be a little bit of overload at the first sight. But this makes it all very flexible and customizable. 

- fl_activationpage: Template which checks the activation code sent by the user to activate the account
- fl_deleteaccountpage: Template, which contains a form to enter the password to delete the account
- fl_deleterequestpage: Template, which contains a form to enter to password to request a deletion link for the account
- fl_forgotlogindatapage: Template, which contains a form to enter the email address if you have forgotten the login data
- fl_recoverylogindatapage: Template, which contains a form where you can enter your new login data
- fl_registerpage: Template which contains the registration form.
- fl_loginpage: Template which contains the login form.
- fl_logoutpage: Template which contains the session logout.
- fl_profilepage: Template which contains the profile form if the user is logged in.
- fl_unlockaccountpage: Template which contains the form to unlock the account by using the password.

### Creation of 10 new pages

At the last step 10 new pages, which use the templates above, will be created under the homepage. These pages are for
the workflow to let users register, login, logout, change their profile data and so on.

- Account activation page
- Account deletion page
- Account deletion confirmation page
- Registration page
- Login page
- Logout page
- Profile page
- Login data forgotten page
- Create new login data page
- Unlock account page

Which pages are published and which are not depends on your module settings. If you decide to offer only a login functionality without the option to register, the registration and activation page will not be published.

#### Support for SeoMaestro
If you have installed the fabulous SeoMaestro module by Wanze, this module set some default values for all pages,
that were created by this module to the seo field too. This is very comfortable, because you do not have to take care
of it until you want to change some values. The following values will be set automatically to all pages during the
installation process of this module and during the installation of a module translation file:

- title: The title will be added to the seo field (multilingual)
- description: The description will be added to the seo field (multilingual)
- priority: The priority for indexing of the page will be set
- sitemap: If the page should be included in the sitemap or not will be set
- noFollow: NoFollow attribute will be set or not
- noIndex: NoIndex attribute will be set or not

Please note: All the default values that will be set during the installation of this module or during the installation
process of a language file are my own preferences - you can change them whenever you want later on.

## Security features

### Double opt-in for newly registered users
To prevent fake registration, every new registered user will get a confirmation email which contains an activiation link to verify his account. After the user has acitvated his account by clicking on the link, the status of this account will change to "confirmed" and the user will be able to login.
At the backend you can see the account confirmation inside the user table.
If an account is confirmed, than the date and time of the confirmation will be displayed in the table.

![alt text](https://github.com/juergenweb/FrontendLoginRegister/blob/main/images/usertable.png?raw=true)

Just to mention: The verification date will only be added to users which verified their account via email, not at users created in the backend.

### Completely lock accounts by suspicious behavior
This is for the case if someone tries to login with same username or email (depending on your settings) and different variations of passwords.

Example: A user tries to login with the following credentials using email and password:

* test@example/password1
* test@example/password2
* test@example/password3
* test@example/password4
* test@example/password4

As you can see, always the same email address with some password variations was used to try a login. To prevent such an attempt to hack an account, this module identifies a behaviour where same email address (or same username) was used.

By default, the number of max. attempts is set to 5. So after the 5th attempt, the module checks if a user account with the given email address exists. If yes, then a lock code will be written inside the database to the appropriate user. The user will get an email containing the information about the locked account and a link to unlock it again.
If a user has an entry with the lock code inside the database, it is no longer possible to login to the account until the lock code will be removed by using the unlock link.
This measure is intended to protect access to user data.

## Sending emails
This module sends a lot of emails for the communication with the user. Just to mention: if you are running a multilanguage site, the emails will always be send in the stored user language and not in the site language. So this guarantee that the user will always get the email in his own language.

### Activation email
This mail will be send if a new user has registered. It contains an activation link to verify the registration. 

### TFA email
If TFA is enabled, this email sends the TFA code for the login. The user has to enter this code inside the login form.

### Reminder email
Optional you can send a reminder email to an user with an unverified account. This mail should remind him to activate his account. It includes the link for the activation (same as the activation email)

### Password forgotten email
This mail sends a link to create new login data if the user has forgotten them. The user has to enter his email address inside the form

### Deletion email
If you allow your users, that they can delete their account, this mail sends an deletion link to the user after the user has entered his password inside a form.

### Deletion confirmation email
If an unverified account has been deleted, the user will get an email that his account is deleted now.

## Unlock account email
If suspicious actions were detected at the login section, then the affected user account will be locked via a code inside the database. This mail contains a link to unlock the account again. The user has to click the link inside the mail and has to enter his password on the page.

## Customizing the email texts
This module adds pre-defined texts for the emails, but you are able to change them to your needs. To include activation links, codes and so on, you have to use placeholders inside your texts. Take a look at the following example:

![alt text](https://github.com/juergenweb/FrontendLoginRegister/blob/main/images/deletion-mail-example.png?raw=true)

In the image above you can see how to use the placeholders. They are surrounded by double brackets. You will find an explanation of the placeholders used under each input field.

There are also some other placeholder that can be used:

![alt text](https://github.com/juergenweb/FrontendLoginRegister/blob/main/images/generalplaceholders.png?raw=true)

You will find this list at the bottom of the configuration page.


## Support for custom user fields
If you have added additional input fields to the user template (fe name, surname,...), they will also be selectable for the registration and profile form on the frontend. 
This means, that you can add them in the module configuration to the profile or registration form and they will be rendered on the frontend too (like email, password,..)
There is only one restriction: Only Processwire inputfields of the follwing types will be supported at the moment:

* FieldtypeEmail
* FieldtypePassword,
* FieldtypeText
* FieldtypeTextarea
* FieldtypeCheckbox
* FieldtypeInteger
* FieldtypeFloat
* FieldtypeDatetime
* FieldtypeURL
* FieldtypePage

All other field types will be ignored and cannot be selected.

In this case first name and surname were added to the user template:

![alt text](https://github.com/juergenweb/FrontendLoginRegister/blob/main/images/profile.png?raw=true)

And in the configuration setting of the registration or profile form these fields can be added as well.

![alt text](https://github.com/juergenweb/FrontendLoginRegister/blob/main/images/customfields.png?raw=true)

You can change the order of the fields by dragging them to the prefered position.

Just to mention: Only fields, which are editable by an user will be displayed. Fields which are added to the user template, but the user has not the right to edit it will not be displayed. So if you are adding custom fields to the user template take care that you set the status to editable. 

## Multi-language
The module will be shipped with the German translations (default is English).
After installing a language file all titles and descriptions in that language will be added to the pages (and to SeoMaestro fields if installed).

As an extra addition I have added a checkbox to each language import field. This is only the case with this module. This checkbox is for importing the texts for the emails too. 

![alt text](https://github.com/juergenweb/FrontendLoginRegister/blob/main/images/language-import-fields.png?raw=true)

If you are select fe de for the German language and you will mark the checkbox beside this input too, then the German texts for the emails will be saved to the appropriate textareas too. But be careful, because this will overwrite all of your texts written before, but this is a real time saver. 

![alt text](https://github.com/juergenweb/FrontendLoginRegister/blob/main/images/customfields.png?raw=true)

At the moment there is unfortunately only one file for the German texts and not for other languages, but maybe other users send me some translations that I can add in the future.

## ToDo
Testing, testing, testing....

This read me file will be extended time after time - so please be patient ;-)
