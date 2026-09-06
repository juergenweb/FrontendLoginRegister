<?php

declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Small collection of pure, wire()-free text helper functions.
 *
 * These are deliberately kept free of any ProcessWire dependency (no wire(), no
 * $this->, no ProcessWire classes) so they can be unit-tested in isolation without
 * a bootstrapped ProcessWire instance - see tests/Unit/TextHelperTest.php.
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: TextHelper.php
 */
class TextHelper
{
    /**
     * Make better page paths by beautifying the slug by replacing non-allowed characters
     * @param string|null $text
     * @return string
     */
    public static function convertUmlauts(?string $text): string
    {
        if (is_null($text)) {
            return '';
        }
        // convert all to lowercase first
        // Use mb_strtolower() (not strtolower()) so that multi-byte UTF-8 characters
        // like the uppercase umlauts Ä/Ö/Ü are lowercased too. Plain strtolower() only
        // understands single-byte ASCII, so an uppercase umlaut would slip past it
        // unchanged and then past the str_replace() calls below (which only match the
        // lowercase forms), leaving it to be transliterated by iconv() instead - and
        // that transliteration is NOT consistent across platforms/iconv libraries (eg.
        // "Ä" becomes "A" on one system and "A (a literal quote plus A) on another),
        // which made this method produce different slugs depending on the server it
        // runs on. Lowercasing properly first makes the result deterministic everywhere.
        $text = mb_strtolower($text);

        // Replace German umlauts first
        $text = str_replace("ä", "ae", $text);
        $text = str_replace("ü", "ue", $text);
        $text = str_replace("ö", "oe", $text);
        $text = str_replace("ß", "ss", $text);

        // Replace all others and output it
        return iconv("utf-8", "ASCII//TRANSLIT", $text);
    }

    /**
     * Convert the values of a text box to an array
     * Each value has to be written on a new line
     * @param string|null $textarea - the value of the textarea field
     * @return array
     */
    public static function newLineToArray(?string $textarea = null): array
    {
        // remove extra spaces from each array value
        return array_map('trim', explode("\n", (string)$textarea));
    }
}
