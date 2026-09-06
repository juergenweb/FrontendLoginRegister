<?php

declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Trait bundling the "verification code" concern shared by the activation, deletion,
 * password-recovery and account-unlock flows: building the emailed link that carries a random
 * code as a querystring parameter, reading that querystring back on the target page, and
 * looking up the user the code belongs to.
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: CreatesVerificationLinks.php
 */

use FrontendForms\Link;
use ProcessWire\User;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;
use function ProcessWire\__;

trait CreatesVerificationLinks
{
    /**
     * Calculate the difference in days between now and the deletion date
     * Pure logic lives in FrontendLoginRegister\TimeHelper::daysToDelete() (unit-tested
     * there) - this method just resolves $user->created and the module configuration.
     * @param User $user
     * @return int
     */
    protected function daysToDelete(User $user): int
    {
        return TimeHelper::daysToDelete($user->created, (int)$this->loginregisterConfig['input_delete']);
    }

    /**
     * Create an activation link
     * @param User $user
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function createActivationLink(User $user): string
    {
        return $this->createCodeLink('fl_activationpage', $user->fl_activation, null,
            $this->_('I have registered for an account - activate my account now'));
    }

    /**
     * Create a deletion link if the user has not registered
     * @param User $user
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function createNotRegisteredLink(User $user): string
    {
        return $this->createCodeLink('fl_activationpage', $user->fl_activation, 'notregisteredcode',
            $this->_('I have not registered for an account - delete the account'));
    }

    /**
     * Base method for creating a link with a random code
     * @param string $templateName
     * @param string $code - the random code itself
     * @param string|null $querystringname
     * @param string|null $linktext
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function createCodeLink(
        string $templateName,
        string $code,
        string|null $querystringname = null,
        string|null $linktext = null
    ): string {
        $targetPage = $this->wire('pages')->get('template=' . $templateName . ',include=all');
        if (is_null($querystringname)) {
            $codeName = substr($templateName, 3); // remove the "fl_" prefix
            $codeName = str_replace('page', 'code', $codeName); // replace the string "page" with "code"
        } else {
            $codeName = $querystringname;
        }
        $link = new Link();
        $link->setUrl($targetPage->httpUrl);
        $link->setQueryString($codeName . '=' . $code);
        $link_text = (!is_null($linktext)) ? $linktext : $targetPage->httpUrl;
        $link->setLinkText($link_text);
        return $link->render();
    }

    /**
     * Check if a query string with a specific name is present in the redirect URL
     * @param string $queryStringName - the name for the querystring parameter
     * @param string|bool $redirect - true (default): redirect to the site's real homepage URL
     *     if the querystring is missing; false: do not redirect at all; a string: redirect to
     *     this specific URL instead
     * @return string - returns the sanitized query string if present, otherwise redirect
     * @throws WireException
     */
    protected function checkForQueryString(string $queryStringName, string|bool $redirect = true): string
    {
        // get the query string
        $queryString = $this->wire('input')->queryStringClean(['validNames' => [$queryStringName]]);
        $this->queryString = str_replace($queryStringName . '=', '', $queryString);
        if (!$this->queryString && $redirect) {
            // Resolve the real homepage URL instead of hardcoding "/": on a ProcessWire
            // installation that lives in a subdirectory (common on shared hosting), "/" would
            // point above the site root instead of at its actual homepage.
            $url = is_string($redirect) ? $redirect : $this->wire('pages')->get(1)->url;
            $this->wire('session')->redirect($url . $this->segments);
        }
        return $this->queryString;
    }

    /**
     * Check if a user with a certain code exists
     * @param string $codeName
     * @return boolean - true or false
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function checkForUser(string $codeName): bool
    {
        if ($this->checkQueryStringUser($codeName)) {
            return true;
        }
        $this->setAlert('alert_dangerClass', __('Sorry, but no user was found with this code.'));
        $this->showForm = false;
        return false;
    }

    /**
     * Check if a user exists with this querystring (code) in the database
     * @param string $querystringParameterName
     * @return bool
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function checkQueryStringUser(string $querystringParameterName): bool
    {
        $fieldName = str_replace('code', '', $querystringParameterName); // remove the string "code" from the string

        $user = $this->wire('users')->get(
            'fl_' . $fieldName . '=' . $this->wire('sanitizer')->selectorValue($this->queryString)
        );
        if ($user->id != 0) {
            $this->user = $user; // set the user object to the property user
            return true;
        }
        return false;
    }
}
