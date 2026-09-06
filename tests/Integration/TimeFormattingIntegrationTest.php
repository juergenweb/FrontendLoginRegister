<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/LoginPageTimeHarness.php';

/**
 * Integration coverage for LoginPage::secondsToReadable() and LoginPage::secondsToTime(). Both
 * delegate their pure arithmetic to TimeHelper (already unit-tested there), but still own the
 * label lookup, pluralization/unit-selection and "and"-joining themselves, entangled with
 * $this->_()/$this->_n() translation calls - which is what these tests actually cover.
 *
 * Assertions deliberately never hardcode the translated label/unit words (e.g. "hour", "and"):
 * this suite runs against whatever real ProcessWire instance and active language the developer
 * configured, so a hardcoded English word would fail on a site with translations loaded. Instead,
 * assertions check the language-independent parts of the output - the numbers themselves, their
 * order, and the zero-padded/colon-joined structure - which is exactly the logic these two
 * methods are responsible for on top of the (already-covered) pure arithmetic.
 */
final class TimeFormattingIntegrationTest extends IntegrationTestCase
{
    public function testSecondsToReadableReturnsEmptyStringForZeroSeconds(): void
    {
        $page = new LoginPageTimeHarness();

        $this->assertSame('', $page->secondsToReadable(0));
    }

    public function testSecondsToReadableIncludesOnlyTheSingleNonZeroComponent(): void
    {
        // exactly 1 hour, every other component (month/week/day/minute/second) is zero
        $page = new LoginPageTimeHarness();
        $result = $page->secondsToReadable(3600);

        preg_match_all('/\d+/', $result, $matches);
        $this->assertSame(['1'], $matches[0]);
        // more than just the bare number - the (untranslated-assumption-free) label must follow it
        $this->assertGreaterThan(strlen('1'), strlen(trim($result)));
    }

    public function testSecondsToReadableJoinsTwoComponentsInTheCorrectOrder(): void
    {
        // exactly 2 hours and 30 minutes, every other component is zero
        $page = new LoginPageTimeHarness();
        $result = $page->secondsToReadable(9000);

        preg_match_all('/\d+/', $result, $matches);
        $this->assertSame(['2', '30'], $matches[0]);
    }

    public function testSecondsToTimeShowsOnlySecondsWhenBelowOneMinute(): void
    {
        $harness = new LoginPageTimeHarness();
        $result = $harness->callSecondsToTime(45);

        $parts = explode(' ', $result, 2);
        $this->assertSame('45', $parts[0]);
        $this->assertNotEmpty($parts[1] ?? null);
    }

    public function testSecondsToTimeDropsTheHoursComponentWhenZero(): void
    {
        // 2 minutes, 5 seconds - hours component is zero and must be omitted entirely
        $harness = new LoginPageTimeHarness();
        $result = $harness->callSecondsToTime(125);

        $parts = explode(' ', $result, 2);
        $this->assertSame('02:05', $parts[0]);
    }

    public function testSecondsToTimeKeepsAllThreeComponentsZeroPaddedWhenHoursArePresent(): void
    {
        // 1 hour, 1 minute, 1 second
        $harness = new LoginPageTimeHarness();
        $result = $harness->callSecondsToTime(3661);

        $parts = explode(' ', $result, 2);
        $this->assertSame('01:01:01', $parts[0]);
    }
}
