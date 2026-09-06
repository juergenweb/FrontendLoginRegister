<?php

declare(strict_types=1);

namespace FrontendLoginRegister;

use DateTime;

/*
 * Small collection of pure, wire()-free time/date helper functions.
 *
 * These are deliberately kept free of any ProcessWire dependency (no wire(), no
 * $this->, no ProcessWire classes) so they can be unit-tested in isolation without
 * a bootstrapped ProcessWire instance - see tests/Unit/TimeHelperTest.php.
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: TimeHelper.php
 */
class TimeHelper
{
    /**
     * Break a number of seconds down into hours/minutes/seconds components.
     * Pure integer arithmetic extracted from LoginPage::secondsToTime(), which still owns the
     * zero-padding, leading-component-dropping and translation-unit-selection logic itself,
     * since those are entangled with $this->_()/$this->_n() translation calls and can't be made
     * wire()-free.
     * @param int $seconds
     * @return array{hours: int, minutes: int, seconds: int}
     */
    public static function secondsToTimeParts(int $seconds): array
    {
        $sec = $seconds % 60;
        $totalMinutes = (int)($seconds / 60);

        $minutes = $totalMinutes % 60;
        $hours = (int)($totalMinutes / 60);

        return ['hours' => $hours, 'minutes' => $minutes, 'seconds' => $sec];
    }

    /**
     * Break a number of seconds down into month/week/day/hour/minute/second components.
     * Pure integer arithmetic extracted from LoginPage::secondsToReadable(), which still owns
     * the label lookup, $this->_n() pluralization and "and"-before-the-last-item joining, since
     * those are entangled with translation calls and can't be made wire()-free. A "month" here
     * is a fixed 30-day (2592000s) unit, not a calendar month - matching the original
     * implementation.
     *
     * The original implementation computed 'day' as floor(($ss % 604800) / 86400) - the
     * remainder of the FULL $ss modulo one week, rather than the remainder left after months
     * were already subtracted. For $ss below one month (2592000s) that happens to give the same
     * result, but from 30 days upward it produces a wrong day count (eg. exactly 30 days came
     * out as "1 month, 2 days" instead of "1 month"). Fixed here by reducing modulo the month
     * length first.
     * @param int $ss
     * @return array{month: int, week: int, day: int, hour: int, minute: int, second: int}
     */
    public static function secondsToReadableParts(int $ss): array
    {
        return [
            'month' => intdiv($ss, 2592000),
            'week' => intdiv($ss % 2592000, 604800),
            'day' => intdiv(($ss % 2592000) % 604800, 86400),
            'hour' => intdiv($ss % 86400, 3600),
            'minute' => intdiv($ss % 3600, 60),
            'second' => $ss % 60,
        ];
    }

    /**
     * Calculate the total number of days between a registration timestamp and "now", plus a
     * configured number of additional days - used to tell a not-yet-activated user how many days
     * are left before their account is auto-deleted.
     * Pure logic extracted from CreatesVerificationLinks::daysToDelete(), which still owns
     * reading $user->created and the module configuration.
     * @param int $createdTimestamp - the user's registration date, as a unix timestamp
     * @param int $inputDeleteDays - the configured number of additional days (input_delete)
     * @param int|null $now - the current unix timestamp; defaults to time() - overridable for tests
     * @return int
     */
    public static function daysToDelete(int $createdTimestamp, int $inputDeleteDays, ?int $now = null): int
    {
        $now = $now ?? time();

        $registration = new DateTime();
        $registration->setTimestamp($createdTimestamp);

        $current = new DateTime();
        $current->setTimestamp($now);

        $diff = $registration->diff($current);
        $diffDays = (int)$diff->format('%a');

        return $diffDays + $inputDeleteDays;
    }
}
