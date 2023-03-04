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
        return $this->createCodeLink('fl_activationpage', $user->fl_activation);
    }

    /**
     * Base method for creating a link with a random code
     * @param string $templateName
     * @param string $code - the random code itself
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function createCodeLink(string $templateName, string $code): string
    {
        $targetPage = $this->wire('pages')->get('template=' . $templateName.',include=all');
        $codeName = substr($templateName, 3);// remove  "fL_"
        $codeName = str_replace('page', 'code', $codeName); // replace the string "Page" with "code"
        $link = new Link();
        $link->setUrl($targetPage->httpUrl);
        $link->setQueryString($codeName . '=' . $code);
        $link->setLinkText($targetPage->httpUrl);
        return $link->___render();
    }

}
