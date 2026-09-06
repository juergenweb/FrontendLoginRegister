<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Unit;

// Loaded directly instead of relying on Composer's classmap autoloading for it -
// see the note in the project chat about why (Composer on this particular Windows
// setup wasn't picking helpers/ up via the "classmap" autoload key).
require_once __DIR__ . '/../../helpers/TimeHelper.php';

use FrontendLoginRegister\TimeHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * Unit tests for FrontendLoginRegister\TimeHelper.
 * These need no ProcessWire bootstrap at all - TimeHelper is pure PHP.
 */
final class TimeHelperTest extends TestCase
{
    public static function secondsToTimePartsProvider(): array
    {
        return [
            'zero seconds' => [0, ['hours' => 0, 'minutes' => 0, 'seconds' => 0]],
            'plain seconds only' => [45, ['hours' => 0, 'minutes' => 0, 'seconds' => 45]],
            'exactly one minute' => [60, ['hours' => 0, 'minutes' => 1, 'seconds' => 0]],
            'minutes and seconds' => [125, ['hours' => 0, 'minutes' => 2, 'seconds' => 5]],
            'exactly one hour' => [3600, ['hours' => 1, 'minutes' => 0, 'seconds' => 0]],
            'hours, minutes and seconds' => [3725, ['hours' => 1, 'minutes' => 2, 'seconds' => 5]],
            // the TFA code expiry default (10 minutes) used throughout the module
            'ten minutes' => [600, ['hours' => 0, 'minutes' => 10, 'seconds' => 0]],
            'just under two hours' => [7199, ['hours' => 1, 'minutes' => 59, 'seconds' => 59]],
        ];
    }

    #[DataProvider('secondsToTimePartsProvider')]
    public function testSecondsToTimePartsBreaksDownCorrectly(int $seconds, array $expected): void
    {
        $this->assertSame($expected, TimeHelper::secondsToTimeParts($seconds));
    }

    public function testDaysToDeleteAddsConfiguredDaysToTheElapsedDaysSinceRegistration(): void
    {
        $now = strtotime('2026-01-15 12:00:00');
        $registeredAt = strtotime('2026-01-10 08:00:00'); // 5 days before $now

        $this->assertSame(5 + 3, TimeHelper::daysToDelete($registeredAt, 3, $now));
    }

    public function testDaysToDeleteWithZeroElapsedDays(): void
    {
        $now = strtotime('2026-01-15 12:00:00');
        $registeredAt = $now; // registered right now

        $this->assertSame(7, TimeHelper::daysToDelete($registeredAt, 7, $now));
    }

    public function testDaysToDeleteWithZeroConfiguredDeleteDays(): void
    {
        $now = strtotime('2026-01-15 12:00:00');
        $registeredAt = strtotime('2026-01-01 12:00:00'); // 14 days before $now

        $this->assertSame(14, TimeHelper::daysToDelete($registeredAt, 0, $now));
    }

    public function testDaysToDeleteDefaultsToCurrentTimeWhenNowIsNotGiven(): void
    {
        $registeredAt = time() - (2 * 86400); // registered 2 days ago

        // no $now argument - falls back to time() internally
        $this->assertSame(2 + 1, TimeHelper::daysToDelete($registeredAt, 1));
    }

    public static function secondsToReadablePartsProvider(): array
    {
        return [
            'zero seconds' => [0, ['month' => 0, 'week' => 0, 'day' => 0, 'hour' => 0, 'minute' => 0, 'second' => 0]],
            'seconds only' => [200, ['month' => 0, 'week' => 0, 'day' => 0, 'hour' => 0, 'minute' => 3, 'second' => 20]],
            'just under one minute' => [59, ['month' => 0, 'week' => 0, 'day' => 0, 'hour' => 0, 'minute' => 0, 'second' => 59]],
            'just under one hour' => [3599, ['month' => 0, 'week' => 0, 'day' => 0, 'hour' => 0, 'minute' => 59, 'second' => 59]],
            'just under one day' => [86399, ['month' => 0, 'week' => 0, 'day' => 0, 'hour' => 23, 'minute' => 59, 'second' => 59]],
            'just under one week' => [604799, ['month' => 0, 'week' => 0, 'day' => 6, 'hour' => 23, 'minute' => 59, 'second' => 59]],
            'exactly one week' => [604800, ['month' => 0, 'week' => 1, 'day' => 0, 'hour' => 0, 'minute' => 0, 'second' => 0]],
            // Regression cases for the "day computed from the wrong remainder" bug: the
            // original implementation derived 'day' from $ss % 604800 (a straight weekly
            // remainder of the full duration) instead of from the remainder left after months
            // were already subtracted, so exactly 30 days (one "month" here, not a calendar
            // month) used to come out as "1 month, 2 days" instead of just "1 month".
            'exactly one month, no leftover days' => [2592000, ['month' => 1, 'week' => 0, 'day' => 0, 'hour' => 0, 'minute' => 0, 'second' => 0]],
            'one month plus two days' => [2764800, ['month' => 1, 'week' => 0, 'day' => 2, 'hour' => 0, 'minute' => 0, 'second' => 0]],
            'one month, one week and three days' => [3456000, ['month' => 1, 'week' => 1, 'day' => 3, 'hour' => 0, 'minute' => 0, 'second' => 0]],
            'all components non-zero' => [
                2592000 + 604800 + (2 * 86400) + (3 * 3600) + (4 * 60) + 5,
                ['month' => 1, 'week' => 1, 'day' => 2, 'hour' => 3, 'minute' => 4, 'second' => 5],
            ],
        ];
    }

    #[DataProvider('secondsToReadablePartsProvider')]
    public function testSecondsToReadablePartsBreaksDownCorrectly(int $seconds, array $expected): void
    {
        $this->assertSame($expected, TimeHelper::secondsToReadableParts($seconds));
    }
}
