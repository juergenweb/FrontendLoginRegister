<?php

declare(strict_types=1);

namespace FrontendLoginRegister;

/*
 * Small collection of pure, wire()-free array helper functions.
 *
 * These are deliberately kept free of any ProcessWire dependency (no wire(), no
 * $this->, no ProcessWire classes) so they can be unit-tested in isolation without
 * a bootstrapped ProcessWire instance - see tests/Unit/ArrayHelperTest.php.
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: ArrayHelper.php
 */
class ArrayHelper
{
    /**
     * Search the multidimensional array containing the translations for the given hash
     * and output the text in the given language
     * Expects each $array item to be an indexed array where index 4 holds the hash to
     * compare against and index 1 holds the value to return on a match.
     * @param string $val
     * @param array $array
     * @return string|null
     */
    public static function searchMultiArray(string $val, array $array): ?string
    {
        foreach ($array as $item) {
            if (($item[4] ?? null) === $val) {
                return $item[1];
            }
        }
        return null;
    }

    /**
     * Find which of the given mandatory fields are missing from a submitted set of field
     * names.
     * Pure logic extracted from FrontendLoginRegister::getMissingFields() - that method's
     * own $field parameter was never used inside its body (only for the docblock's type
     * hint), so nothing ProcessWire-specific was actually needed here.
     * @param array $mandatory_fields - [fieldID => label, ...] - the fields that should be present
     * @param array $fields_values - the field IDs that were actually selected/submitted
     * @return array - the subset of $mandatory_fields whose keys are missing from $fields_values
     */
    public static function filterMissingMandatoryFields(array $mandatory_fields, array $fields_values): array
    {
        $keys = array_keys($mandatory_fields);
        $result = array_diff($keys, $fields_values);
        $filtered = [];
        if ($result) {
            $filtered = array_intersect_key($mandatory_fields, array_flip($result));
        }
        return $filtered;
    }

    /**
     * Recursively collect all keys of a (possibly nested) array, keeping only the
     * non-numeric (string) ones.
     * Pure logic extracted from LoginPage::arrayKeysMulti() - used there to find which
     * username/email values a session's failed-login-attempts array was keyed by, so
     * numeric (list-index) keys are noise that has to be filtered out.
     * @param array $array
     * @return array - the non-numeric keys found anywhere in the array, reindexed from 0
     */
    public static function arrayKeysMulti(array $array): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $keys[] = $key;

            if (is_array($value)) {
                $keys = array_merge($keys, self::arrayKeysMulti($value));
            }
        }

        // filter out all numeric values
        $keys = array_filter($keys, fn ($item) => !is_int($item));

        return array_values($keys);
    }
}
