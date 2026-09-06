<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/SendsLocalizedMailHarness.php';

/**
 * Integration coverage for SendsLocalizedMail::generateNoReplyText(), which builds the static
 * "do not reply" footer markup appended to outgoing emails. No wire() calls, no mail sending -
 * this only checks that the returned markup contains the placeholder later replaced with the
 * actual "do not reply" text.
 */
final class GenerateNoReplyTextIntegrationTest extends IntegrationTestCase
{
    public function testReturnsMarkupContainingTheDoNotReplyPlaceholder(): void
    {
        $harness = new SendsLocalizedMailHarness();
        $result = $harness->callGenerateNoReplyText();

        $this->assertIsString($result);
        $this->assertStringContainsString('[[DONOTREPLAYVALUE]]', $result);
        $this->assertStringContainsString('<table', $result);
    }
}
