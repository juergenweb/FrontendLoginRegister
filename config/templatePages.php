<?php
declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Holds the array of all pages data
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: templatePages.php
 * Created: 06.07.2022
 */

$this->templatePages = [
    'fl_registerpage' => [
        'title' => $this->_('Registration'),
        'body' => '',
        'description' => $this->_('Register for an account and get access to non-public pages. It is free.'),
        'priority' => '0.1',
        'sitemap' => 1,
        'noFollow' => 1,
        'noIndex' => 1,
        'roles' => ['37','1020'],
        'className' => $this->getAllClasses()['fl_registerpage']
    ],
    'fl_loginpage' => [
        'title' => $this->_('Login'),
        'body' => '',
        'description' => $this->_('On this site you can login, if you have signed up for an account.'),
        'priority' => '0.1',
        'sitemap' => 1,
        'noFollow' => 1,
        'noIndex' => 1,
        'roles' => ['37','1020'],
        'className' => $this->getAllClasses()['fl_loginpage']
    ],
    'fl_logoutpage' => [
        'title' => $this->_('Logout'),
        'body' => '',
        'description' => $this->_('This is the logout page to left the private area if you are logged in.'),
        'priority' => '0.1',
        'sitemap' => 0,
        'noFollow' => 1,
        'noIndex' => 1,
        'roles' => ['37','1020'],
        'className' => $this->getAllClasses()['fl_logoutpage']
    ],
    'fl_profilepage' => [
        'title' => $this->_('Profile'),
        'body' => '',
        'description' => $this->_('On this page you can update your personal data such as password, email address and more.'),
        'priority' => '0.1',
        'sitemap' => 0,
        'noFollow' => 1,
        'noIndex' => 1,
        'roles' => ['1020'],
        'className' => $this->getAllClasses()['fl_profilepage']
    ],
    'fl_forgotlogindatapage' => [
        'title' => $this->_('Request your login data'),
        'body' => '',
        'description' => $this->_('If you have forgotten your login data, please enter your email address and you will get an email to reset your login data.'),
        'priority' => '0.1',
        'sitemap' => 1,
        'noFollow' => 1,
        'noIndex' => 1,
        'status' => 'hidden',
        'roles' => ['37','1020'],
        'className' => $this->getAllClasses()['fl_forgotlogindatapage']
    ],
    'fl_recoverylogindatapage' => [
        'title' => $this->_('Create new login data'),
        'body' => '',
        'description' => $this->_('If you have forgotten your login data you can request on this page for an email to get a link to create the new login data.'),
        'priority' => '0.1',
        'sitemap' => 0,
        'noFollow' => 1,
        'noIndex' => 1,
        'status' => 'hidden',
        'roles' => ['37','1020'],
        'className' => $this->getAllClasses()['fl_recoverylogindatapage']
    ],
    'fl_activationpage' => [
        'title' => $this->_('Account activation'),
        'body' => '',
        'description' => $this->_('On this page your account will be activated if you have sent the correct activation code.'),
        'priority' => '0.1',
        'sitemap' => 0,
        'noFollow' => 1,
        'noIndex' => 1,
        'status' => 'hidden',
        'roles' => ['37','1020'],
        'className' => $this->getAllClasses()['fl_activationpage']
    ],
    'fl_deleterequestpage' => [
        'title' => $this->_('Account deletion'),
        'body' => $this->_('If you want to delete your account permanently, enter your password in the input field below. Then you will get a link, which is valid for 5 minutes, to delete your account.'),
        'description' => $this->_('On this page you can request for a deletion link to delete your account permanently.'),
        'priority' => '0.1',
        'sitemap' => 0,
        'noFollow' => 1,
        'noIndex' => 1,
        'roles' => ['1020'],
        'className' => $this->getAllClasses()['fl_deleterequestpage']
    ],
    'fl_deleteaccountpage' => [
        'title' => $this->_('Account deletion confirmation'),
        'body' => '',
        'description' => $this->_('Confirmation page after clicking the deletion link.'),
        'priority' => '0.1',
        'sitemap' => 0,
        'noFollow' => 1,
        'noIndex' => 1,
        'status' => 'hidden',
        'roles' => ['37','1020'],
        'className' => $this->getAllClasses()['fl_deleteaccountpage']
    ],
    'fl_unlockaccountpage' => [
        'title' => $this->_('Unlock your account'),
        'body' => $this->_('Please enter your password to unlock your account.'),
        'description' => $this->_('Unlock your account after it was locked due to multiple unsuccessful attempts to login.'),
        'priority' => '0.1',
        'sitemap' => 0,
        'noFollow' => 1,
        'noIndex' => 1,
        'status' => 'hidden',
        'roles' => ['37','1020'],
        'className' => $this->getAllClasses()['fl_unlockaccountpage']
    ]
];
