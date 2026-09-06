<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

use FrontendLoginRegister\CreatesVerificationLinks;
use ProcessWire\User;
use ProcessWire\WireData;

/**
 * Minimal real-Wire host for exercising CreatesVerificationLinks's methods against a live
 * ProcessWire instance, without dragging in the full FrontendForms-derived page classes
 * (ActivationPage, DeleteAccountPage, ...) that normally use this trait - those need a lot of
 * unrelated setup (FrontendForms module, form fields, a guest HTTP-like request context) just to
 * construct.
 *
 * The trait's methods only ever touch $this->wire(), $this->queryString, $this->user and
 * $this->loginregisterConfig - all of which this bare-bones harness provides, via WireData for a
 * real wire() and plain public properties standing in for the properties FrontendLoginRegisterPages
 * would normally declare.
 */
class CreatesVerificationLinksHarness extends WireData
{
    use CreatesVerificationLinks;

    public string $queryString = '';
    public $user;
    public array $loginregisterConfig = [];

    /**
     * Public pass-through to the trait's protected checkQueryStringUser(), so the test doesn't
     * need reflection just to call it.
     * @param string $querystringParameterName
     * @return bool
     */
    public function callCheckQueryStringUser(string $querystringParameterName): bool
    {
        return $this->checkQueryStringUser($querystringParameterName);
    }

    /**
     * Public pass-through to the trait's protected createActivationLink().
     * @param User $user
     * @return string
     */
    public function callCreateActivationLink(User $user): string
    {
        return $this->createActivationLink($user);
    }

    /**
     * Public pass-through to the trait's protected createNotRegisteredLink().
     * @param User $user
     * @return string
     */
    public function callCreateNotRegisteredLink(User $user): string
    {
        return $this->createNotRegisteredLink($user);
    }

    /**
     * Public pass-through to the trait's protected createCodeLink().
     * @param string $templateName
     * @param string $code
     * @param string|null $querystringname
     * @param string|null $linktext
     * @return string
     */
    public function callCreateCodeLink(
        string $templateName,
        string $code,
        ?string $querystringname = null,
        ?string $linktext = null
    ): string {
        return $this->createCodeLink($templateName, $code, $querystringname, $linktext);
    }

    /**
     * Public pass-through to the trait's protected daysToDelete().
     * @param User $user
     * @return int
     */
    public function callDaysToDelete(User $user): int
    {
        return $this->daysToDelete($user);
    }
}
