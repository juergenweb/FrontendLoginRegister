<?php
declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Holds the array of all user field data
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: userFields.php
 * Created: 06.07.2022
 */

$this->userFields = [
    'fl_activation' => [
        'fieldtype' => 'FieldtypeText',
        'label' => $this->_('Activation code'),
        'description' => $this->_('This field holds the activation code if account was not activated, otherwise it is empty.')
    ],
    'fl_activationdatetime' => [
        'fieldtype' => 'FieldtypeDatetime',
        'label' => $this->_('Account verified'),
        'description' => $this->_('This field holds the date/time when the user verified his account, otherwise it is empty.')
    ],
    'fl_reminderdatetime' => [
        'fieldtype' => 'FieldtypeDatetime',
        'label' => $this->_('Reminder sent date/time'),
        'description' => $this->_('This field holds the date/time when the reminder email was sent, otherwise it is empty.')
    ],
    'fl_recoverylogindata' => [
        'fieldtype' => 'FieldtypeText',
        'label' => $this->_('Password recovery code'),
        'description' => $this->_('This field holds the recovery code if the user has forgotten his password, otherwise it is empty.')
    ],
    'fl_recoverylogindatadatetime' => [
        'fieldtype' => 'FieldtypeDatetime',
        'label' => $this->_('Password recovery date/time'),
        'description' => $this->_('This field holds the date/time when the recovery code was created, otherwise it is empty.')
    ],
    'fl_deleteaccount' => [
        'fieldtype' => 'FieldtypeText',
        'label' => $this->_('Account deletion code'),
        'description' => $this->_('This field holds the deletion code if the user wants to delete his account, otherwise it is empty.')
    ],
    'fl_deleteaccountdatetime' => [
        'fieldtype' => 'FieldtypeDatetime',
        'label' => $this->_('Account deletion date/time'),
        'description' => $this->_('This field holds the date/time when the deletion code was created, otherwise it is empty.')
    ],
    'fl_unlockaccount' => [
        'fieldtype' => 'FieldtypeText',
        'label' => $this->_('Blocking code'),
        'description' => $this->_('This field holds the blocking code if login failed with same username or email after a certain number of attempts, otherwise it is empty.')
    ],
];
