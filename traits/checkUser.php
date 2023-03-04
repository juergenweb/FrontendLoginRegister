<?php
declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Various methods for user checking
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: checkUser.php
 * Created: 06.07.2022
 */

use ProcessWire\WireException;
use ProcessWire\WirePermissionException;
use function ProcessWire\__;

trait checkUser
{

    /**
     * Check if a user with a certain code exists
     * @param string $codeName
     * @return boolean - true or false
     * @throws WireException
     * @throws WirePermissionException
     */
    private function checkForUser(string $codeName): bool
    {
        if ($this->checkQueryStringUser($codeName)) return true;
        $this->getAlert()
            ->setCSSClass('alert_dangerClass')
            ->setText(__('Sorry, but no user was found with this code.'));
        $this->showForm = false;
        return false;
    }

    /**
     * Check if a user exists with this querystring (code) in the database
     * @param string $querystringParameterName
     * @return int
     * @throws WireException
     * @throws WirePermissionException
     */
    public function checkQueryStringUser(string $querystringParameterName): int
    {
        $fieldName = str_replace('code', '', $querystringParameterName); // remove the string "code" from the string
        $user = $this->wire('users')->get('fl_' . $fieldName . '=' . $this->queryString);
        if($user->id != 0){
            $this->user = $user;
        }
        return $user->id;

    }

}
