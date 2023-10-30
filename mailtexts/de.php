<?php
declare(strict_types=1);

namespace ProcessWire;

/*
 * German texts for the emails
 * These texts will be saved to the database during the installation of the language file
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb 
 * File name: de.php
 * Created: 27.03.2023 
 */

// text for the email including the activation link
$input_activationtext = <<<HEREA
<p>Lieber Nutzer</p>
<p>Sie erhalten diese E-Mail, weil Sie sich für ein Konto bei [[DOMAINVALUE]] am [[CURRENTDATETIMEVALUE]] registriert haben.<br />
Um zu bestätigen, dass Sie die Person sind, die sich auf dieser Website registriert hat, klicken Sie bitte auf den nachfolgenden Link, um Ihr Konto zu aktivieren.</p>
<p>[[VERIFICATIONLINK]]</p>
<p>Nach dieser Überprüfung ist Ihr Konto aktiviert, und Sie können sich mit Ihren Zugangsdaten anmelden.</p>
<p>Wenn Sie sich nicht für ein Konto registriert haben, ignorieren Sie bitte diese E-Mail oder klicken Sie auf den folgenden Link, um das Benutzerkonto zu löschen und keine weiteren E-Mails mehr zu erhalten.</p>
<p>[[NOTREGISTEREDLINK]]</p>
HEREA;

// text for the email including the tfa code
$input_tfatext = <<<HEREA
<p>Lieber Nutzer</p>
<p>Bitte geben Sie den folgenden Code auf dem Bildschirm ein, um sich bei Ihrem Konto anzumelden.</p>
<p><b>[[TFACODE]]</b></p>
<p>Bitte beachten Sie: Dieser Code ist nur für [[EXPIRATIONTIME]] gültig.</p>
HEREA;

// text for the email to remind the user to activate his account
$input_remindertext = <<<HEREA
<p>Lieber Nutzer</p>
<p>Sie erhalten diese E-Mail, weil Sie sich am [[REGISTRATIONDATE]] für einen Account bei [[DOMAINVALUE]] registriert haben.</p>
<p>Leider haben Sie Ihren Account bis heute noch nicht aktiviert.<br />
Ohne Aktivierung wird Ihr Account jedoch am [[DELETEDATE]] automatisch gelöscht.</p>
<p>Um Ihr Konto zu aktivieren, klicken Sie bitte auf den folgenden Link, um Ihre Registrierung abzuschließen:</p>
<p>[[VERIFICATIONLINK]]</p>
<p>Wenn Sie sich nicht für ein Konto registriert haben, ignorieren Sie bitte diese E-Mail oder klicken Sie auf den folgenden Link, um das Benutzerkonto zu löschen und keine weiteren E-Mails mehr zu erhalten.</p>
<p>[[NOTREGISTEREDLINK]]</p>
HEREA;

// text for the email containing a link to reset the login data
$input_passwordforgottentext = <<<HEREA
<p>Lieber Nutzer</p>
<p>Sie erhalten diese E-Mail, weil jemand [[LOGINDATA]] für Ihr Konto bei [[DOMAINVALUE]] angefordert hat.<br />
Aus Sicherheitsgründen versenden wir [[LOGINDATA]] nicht, daher müssen Sie die Zugangsdaten neu erstellen, indem Sie auf den folgenden Link klicken:</p>
<p>[[RECOVERPASSWORDLINK]]</p>
<p>Wenn Sie keine Rücksetzung der Zugangsdaten angefordert haben, ignorieren Sie bitte diese E-Mail.</p>
HEREA;

// text for the email containing a link to delete the account
$input_deleteaccounttext = <<<HEREA
<p>Lieber Nutzer</p>
<p>Sie erhalten diese E-Mail, weil Sie einen Löschungslink für Ihr Konto bei [[DOMAINVALUE]] angefordert haben.<br />
Bitte klicken Sie auf den Link, um Ihr Konto zu löschen. Bitte beachten Sie, dass dieser Link nur 5 Minuten lang gültig ist und nur funktioniert, wenn Sie angemeldet sind.</p>
<p>[[DELETEACCOUNTLINK]]</p>
HEREA;

// text for the email containing the information that the non-verified account is deleted
$input_deletion_confirmation = <<<HEREA
<p>Lieber Nutzer</p>
<p>Wir möchten Sie darüber informieren, dass Ihr Konto bei [[DOMAINVALUE]] nun am [[CURRENTDATETIMEVALUE]] gelöscht wurde.<br />
Der Grund für die Löschung ist, dass Ihr Account seit Ihrer Registrierung am [[REGISTRATIONDATE]] nicht aktiviert wurde.<br />
Um inaktiven Konten vorzubeugen, wird jedes nicht verifizierte Konto nach einer gewissen Zeit automatisch gelöscht.<br />
Wenn Sie sich erneut registrieren möchten, besuchen Sie bitte [[REGISTERURL]] und registrieren Sie sich erneut.</p>
<p>Wir wünschen Ihnen einen schönen Tag!</p>
HEREA;

// text for the mail to inform the user, that his account was blocked due to suspicious activities
$input_unlock_account = <<<HEREA
<p>Lieber Nutzer</p>
<p>Sie erhalten diese E-Mail, weil wir verdächtige Aktivitäten auf Ihrem Benutzerkonto bei [[DOMAINVALUE]] festgestellt haben.<br />
Jemand hat erfolglos versucht, sich in Ihr Konto einzuloggen, indem er Ihren [[LOGINTYPE]] in Kombination mit verschiedenen Passwörtern verwendet hat.<br />
Aus Sicherheitsgründen haben wir Ihr Konto nun gesperrt.<br />
Bitte klicken Sie auf den Link und folgen Sie den Anweisungen auf der weitergeleiteten Seite, um Ihr Konto zu entsperren.<br />
Danach können Sie sich wieder normal einloggen.</p>
<p>[[UNLOCKACCOUNTLINK]]</p>
HEREA;

