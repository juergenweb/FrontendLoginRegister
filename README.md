# FrontendLoginRegister
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![ProcessWire 3](https://img.shields.io/badge/ProcessWire-3.x-orange.svg)](https://github.com/processwire/processwire)

A module for ProcessWire CMS to integrate a user registration/login functionality based on the FrontendForms module.

> ⚠ This module is very Alpha stage - so be aware of using it on live sites!


## Requirements
- ProcessWire 3.0.195 or newer
- PHP 8.0 or newer
- FrontendForms 2.1.15 or newer: Please download and install it from https://github.com/juergenweb/FrontendForms first.
- CronJob: Will be installed automatically, if not installed
- TfaEmail: Only if you want to use 2-factor-authentication with email and code

## Highlights
- "One-click" integration of an user login/registration system without the hazzle of creating all pages by hand
- "One-click" switch between login or login and registration option
- Double opt-in with activation link on new registrations
- Automatic sending of reminder mails, if account has not been activated after a certain time
- Automatic deletion of unverified accounts after a certain time to prevent unused accounts (user will be warned via email before deletion)
- Option to use TFA-Email if installed for higher security on login
- Mutli-language
- Select if you want to login with username and password or email and password
- Select the desired roles for newly created users
- Select, which fields of the user template should be displayed on the registration and profile form (beside the mandatory fields)
- Offer users the possibility to delete their account in the members area using a deletion link with time expiration
- Customize the texts of the emails send by this modules
- Usage of all the benefits of [FrontendForms](https://github.com/juergenweb/FrontendForms#highlights)
- Support for SeoMaestro if installed

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

Some settings will be taken from the FrontendForms module settings (fe enable/disable Captcha, logging failed attempts)
and cannot be set individually in the module configuration of this module globally, but you can always change form
parameters on each template by using the FrontendForms API.

## Changing parameters of each form via API
During the installation of this module a template for each module page will be created and this will look like
this:

```php
// This is the code of the fl_registerpage.php which includes the registration form
            echo '<div id="content">';
            echo $page->body;
            echo $modules->get('FrontendLoginRegister')->RegisterPage();
            echo '</div>';
```
If you want to change some parameters of a form, you can use the FrontendForms API

```php
// This is the code of the fl_registerpage.php which includes the registration form
            echo '<div id="content">';
            echo $page->body;
            $form = $modules->get('FrontendLoginRegister')->RegisterPage();
            $form->setErrorMsg('OMG, there are errors!'); // set a new error message
            $form->disableCaptcha(); // disable the CAPTCHA if it is globally enabled
            $form->setMinTime(10); // set the min time until the form should be submitted to 10 seconds
            echo $form->render();
            echo '</div>';
```

You can find much more infos in the Readme file of the FrontendForms module, so I do not want to go more
into detail at this point.

> ⚠ If you uninstall the module, all manual changes to the templates will be lost, so make a back-up of your templates
if you have made changes before you uninstall the module. Otherwise, you have to write your changes to the templates again.

## What happens during the installation process?

### Creation of 5 new user fields which will be added to the user template

During the installation process of this module 5 new fields will be created:

- fl_activation: Stores the activation code if a new account was created
- fl_recovery: Stores the recovery code if a new password was requested
- fl_recoveryDateTime: Stores the date and time when the recovery code was created
- fl_activationVerified: Holds the date and time when the user has verified his account
- fl_reminderDateTime: Holds the date and time when the reminder email was sent

These 5 fields will be added to the user template, but they are hidden by default, because they are only needed for
the administration of users by this module and not to be filled out by humans.
These fields fe contain activation codes, timestamps and so on... nothing you will need to take care about.

### Creation of 9 new templates

In addition 9 new templates will be created. These templates are needed for the pages, that will be created by this
module.

- fl_activationpage: Template which checks the activation code sent by the user to activate the account
- fl_deleteaccountpage: Template, which contains a form to enter the password to delete the account
- fl_deleterequestpage: Template, which contains a form to enter to password to request a deletion link for the account
- fl_forgotlogindatapage: Template, which contains a form to enter the email address if you have forgotten the login data
- fl_recoverylogindatapage: Template, which contains a form where you can enter your new login data
- fl_registerpage: Template which contains the registration form.
- fl_loginpage: Template which contains the login form.
- fl_logoutpage: Template which contains the session logout.
- fl_profilepage: Template which contains the profile form if the user is logged in.

### Creation of 9 new pages

At the last step 9 new pages, which use the templates above, will be created under the homepage. These pages are for
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

## Double opt-in for newly registered users
To prevent fake registration, every new registered user will get a confirmation email which contains an activiation link to verify his account. After the user has acitvated his account by clicking on the link, the status of this account will change to "confirmed" and the user will be able to login.
At the backend you can see the account confirmation inside the user table.
If an account is confirmed, than the date and time of the confirmation will be displayed in the table.

![alt text](https://github.com/juergenweb/FrontendLoginRegister/blob/main/images/usertable.png?raw=true)

Just to mention: The verification date will only be added to users which verified their account via email, not at users created in the backend.

## Support for SeoMaestro
If you have installed the fabulous SeoMaestro module by Wanze, this module set some default values for all pages,
that will be created by this module to the seo field too. This is very comfortable, because you do not have to take care
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

## Multi-language
The module will be shipped with the German translations (default is English).
After installing a language file all titles and descriptions in that language will be added to the pages (and to SeoMaestro fields if installed).

## ToDo
Testing, testing, testing....

This read me file will be extended time after time - so please be patient ;-)
