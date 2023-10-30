<?php
declare(strict_types=1);

/*
 * English texts for the emails
 * These texts will be saved to the database during the installation of the language file
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb 
 * File name: en.php
 * Created: 27.03.2023 
 */


// text for the email including the activation link
$input_activationtext = <<<HEREA
<p>Dear user</p>
<p>You will receive this email, because you have registered for an account at [[DOMAINVALUE]] on [[CURRENTDATETIMEVALUE]].<br />
To confirm that you are the person who registered on this site, please click the link below to activate your account.</p>
<p>[[VERIFICATIONLINK]]</p>
<p>After verification, your account is activated, and you can log in with your credentials.</p>
<p>If you did not register for an account, please ignore this email or click the following link to delete the user account and to prevent getting further emails.</p>
<p>[[NOTREGISTEREDLINK]]</p>
HEREA;

// text for the email including the tfa code
$input_tfatext = <<<HEREA
<p>Dear user</p>
<p>Please enter the code below on the screen to login into your account.</p>
<p><b>[[TFACODE]]</b></p>
<p>Please note: This code is only valid for [[EXPIRATIONTIME]].</p>
HEREA;

// text for the email to remind the user to activate his account
$input_remindertext = <<<HEREA
<p>Dear user</p>
<p>You are receiving this email because you registered for an account at [[DOMAINVALUE]] on [[REGISTRATIONDATE]].</p>
<p>Unfortunately, you have not activated your account until today. However, you have [[DAYSTODELETE]] left to activate your account before it will be deleted on [[DELETEDATE]].</p>
<p>To activate your account, please click on the following link to complete your registration:</p>
<p>[[VERIFICATIONLINK]]</p>
<p>If you have not registered for an account, please ignore this email or click the following link to delete the account and stop receiving emails.</p>
<p>[[NOTREGISTEREDLINK]]</p>
HEREA;

// text for the email containing a link to reset the login data
$input_passwordforgottentext = <<<HEREA
<p>Dear user</p>
<p>You will receive this email, because someone has requested [[LOGINDATA]] for your account at [[DOMAINVALUE]].<br />
For security reasons, we do not send [[LOGINDATA]], so you have to create new login data by clicking the following link:</p>
<p>[[RECOVERPASSWORDLINK]]</p>
<p>If you did not request a login data reset, please ignore this email.</p>
HEREA;

// text for the email containing a link to delete the account
$input_deleteaccounttext = <<<HEREA
<p>Dear user</p>
<p>You will receive this email, because you have requested a deletion link for your account at [[DOMAINVALUE]].<br />
Please click the link to delete your account, but please note that this link is only valid for 5 minutes, and it only works if you are logged in.</p>
<p>[[DELETEACCOUNTLINK]]</p>
HEREA;

// text for the email containing the information that the non-verified account is deleted
$input_deletion_confirmation = <<<HEREA
<p>Dear user</p>
<p>We want to inform you that your account at [[DOMAINVALUE]] has now been deleted on [[CURRENTDATETIMEVALUE]].<br />
The reason for the deletion is that your account has not been activated since your registration on [[REGISTRATIONDATE]].<br />
To prevent inactive accounts, every account not verified will be deleted after a certain time automatically.<br />
If you want to register again, please visit [[REGISTERURL]] and register once more.</p>
<p>We wish you a nice day!</p>
HEREA;

// text for the mail to inform the user, that his account was blocked due to suspicious activities
$input_unlock_account = <<<HEREA
<p>Dear user</p>
<p>You will receive this email, because we have detected suspicious activity on your user account account at [[DOMAINVALUE]].<br />
Someone has tried unsuccessfully to login into your account by using your [[LOGINTYPE]] in combination with various passwords.<br />
For security reasons we have locked your account now.<br />
Please click the link and follow the instructions on the redirected page to unlock your account.<br />
After that you can log in normally again.</p>
<p>[[UNLOCKACCOUNTLINK]]</p>
HEREA;

