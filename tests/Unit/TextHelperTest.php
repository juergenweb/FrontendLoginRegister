<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Unit;

// Loaded directly instead of relying on Composer's classmap autoloading for it -
// see the note in the project chat about why (Composer on this particular Windows
// setup wasn't picking helpers/ up via the "classmap" autoload key).
require_once __DIR__ . '/../../helpers/TextHelper.php';

use FrontendLoginRegister\TextHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * Unit tests for FrontendLoginRegister\TextHelper.
 * These need no ProcessWire bootstrap at all - TextHelper is pure PHP.
 */
final class TextHelperTest extends TestCase
{
    #[DataProvider('umlautProvider')]
    public function testConvertUmlautsReplacesGermanCharacters(?string $input, string $expected): void
    {
        $this->assertSame($expected, TextHelper::convertUmlauts($input));
    }

    public static function umlautProvider(): array
    {
        return [
            'null input becomes empty string' => [null, ''],
            'empty string stays empty' => ['', ''],
            'lowercase a-umlaut' => ['ä', 'ae'],
            'lowercase o-umlaut' => ['ö', 'oe'],
            'lowercase u-umlaut' => ['ü', 'ue'],
            'sharp s (eszett)' => ['ß', 'ss'],
            'mixed word, all-lowercase umlauts' => ['Straße', 'strasse'],
            'plain ascii is left untouched (besides lowercasing)' => ['Hello World', 'hello world'],
            // convertUmlauts() uses mb_strtolower() (not strtolower()) specifically so that
            // uppercase umlauts are lowercased correctly before the str_replace() calls run.
            // An earlier version used plain strtolower(), which is byte-based and leaves
            // multi-byte UTF-8 characters like 'Ä'/'Ö'/'Ü' untouched; those then fell through
            // to iconv()'s transliteration instead, which is NOT consistent across platforms/
            // iconv libraries (eg. "Ä" became "A" on Linux but "A (a literal quote + A) on
            // Windows in testing here) - these two cases guard against that regressing.
            'uppercase umlaut is normalized the same way as lowercase' => ['Ä', 'ae'],
            'uppercase umlaut inside a longer string' => ['Grüße für Öl-Prüfung', 'gruesse fuer oel-pruefung'],
        ];
    }

    #[DataProvider('newLineProvider')]
    public function testNewLineToArraySplitsAndTrimsLines(?string $input, array $expected): void
    {
        $this->assertSame($expected, TextHelper::newLineToArray($input));
    }

    public static function newLineProvider(): array
    {
        return [
            'null input yields a single empty entry' => [null, ['']],
            'single line, no trailing newline' => ['foo', ['foo']],
            'two lines' => ["foo\nbar", ['foo', 'bar']],
            'surrounding whitespace on each line is trimmed' => ["  foo  \n  bar  ", ['foo', 'bar']],
            'blank lines are preserved as empty strings' => ["foo\n\nbar", ['foo', '', 'bar']],
        ];
    }
}
