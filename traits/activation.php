<?php
declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Various methods for activating a user via email
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: activation.php
 * Created: 06.07.2022
 */

use FrontendForms\Link as Link;
use ProcessWire\User as User;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait activation
{

    /**
     * Create an activation link
     * @param User $user
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function createActivationLink(User $user): string
    {
        return $this->createCodeLink('fl_activationpage', $user->fl_activation, null, $this->_('I have registered for an account - activate my account now'));
    }

    /**
     * Create a deletion link if user has not registered
     * @param User $user
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function createNotRegisteredLink(User $user): string
    {
        return $this->createCodeLink('fl_activationpage', $user->fl_activation, 'notregisteredcode', $this->_('I have not registered for an account - delete the account'));
    }

    /**
     * Base method for creating a link with a random code
     * @param string $templateName
     * @param string $code - the random code itself
     * @param string|null $querystringname
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function createCodeLink(string $templateName, string $code, string|null $querystringname = null, string|null $linktext = null): string
    {
        $targetPage = $this->wire('pages')->get('template=' . $templateName.',include=all');
        if(is_null($querystringname)){
            $codeName = substr($templateName, 3);// remove  "fL_"
            $codeName = str_replace('page', 'code', $codeName); // replace the string "Page" with "code"
        } else {
            $codeName = $querystringname;
        }
        $link = new Link();
        $link->setUrl($targetPage->httpUrl);
        $link->setQueryString($codeName . '=' . $code);
        $linkt_text = (!is_null($linktext))? $linktext : $targetPage->httpUrl;
        $link->setLinkText($linkt_text);
        return $link->___render();
    }

}
