<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';

use DOMDocument;
use ReflectionMethod;

/**
 * Integration coverage for two protected helper methods on the FrontendLoginRegister module class
 * itself: createRolesArray() and appendHTML(). Both are called via Reflection on the module's real,
 * already-bootstrapped singleton instance ($modules->get('FrontendLoginRegister')) rather than by
 * constructing anything - there is no page/redirect risk here at all, unlike the Page subclasses
 * covered elsewhere in this suite.
 */
final class ModuleHelperMethodsIntegrationTest extends IntegrationTestCase
{
    public function testCreateRolesArrayResolvesARealRoleNameToItsId(): void
    {
        $module = $this->wire('modules')->get('FrontendLoginRegister');
        $method = new ReflectionMethod($module, 'createRolesArray');
        $method->setAccessible(true);

        $result = $method->invoke($module, ['guest']);

        $guestRole = $this->wire('roles')->get('name=guest');
        $this->assertSame([$guestRole->id], $result);
    }

    public function testCreateRolesArrayResolvesAnUnknownRoleNameToZero(): void
    {
        $module = $this->wire('modules')->get('FrontendLoginRegister');
        $method = new ReflectionMethod($module, 'createRolesArray');
        $method->setAccessible(true);

        $guestRole = $this->wire('roles')->get('name=guest');
        $result = $method->invoke($module, ['guest', 'flr-test-no-such-role-xyz']);

        // order must be preserved - one real id, one unresolved (NullPage) id of 0
        $this->assertSame([$guestRole->id, 0], $result);
    }

    public function testAppendHTMLImportsEachTopLevelNodeOfTheSourceMarkupIntoTheParent(): void
    {
        $module = $this->wire('modules')->get('FrontendLoginRegister');
        $method = new ReflectionMethod($module, 'appendHTML');
        $method->setAccessible(true);

        $doc = new DOMDocument();
        $parent = $doc->createElement('div');
        $doc->appendChild($parent);

        $method->invoke($module, $parent, '<p>Hello</p><span>World</span>');

        $this->assertSame(2, $parent->childNodes->length);
        $this->assertSame('p', $parent->childNodes->item(0)->nodeName);
        $this->assertSame('Hello', $parent->childNodes->item(0)->textContent);
        $this->assertSame('span', $parent->childNodes->item(1)->nodeName);
        $this->assertSame('World', $parent->childNodes->item(1)->textContent);
    }
}
