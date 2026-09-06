<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Unit;

// Loaded directly instead of relying on Composer's classmap autoloading for it -
// see the note in the project chat about why (Composer on this particular Windows
// setup wasn't picking helpers/ up via the "classmap" autoload key).
require_once __DIR__ . '/../../helpers/ArrayHelper.php';

use FrontendLoginRegister\ArrayHelper;
use PHPUnit\Framework\TestCase;

/*
 * Unit tests for FrontendLoginRegister\ArrayHelper.
 * These need no ProcessWire bootstrap at all - ArrayHelper is pure PHP.
 */
final class ArrayHelperTest extends TestCase
{
    /**
     * Each row mimics one entry of the translations array as produced by
     * writeMailTexts(): index 1 is the value, index 4 is the hash to match against.
     */
    private function sampleTranslations(): array
    {
        return [
            ['x', 'Hallo Welt', 'x', 'x', 'hash-de'],
            ['x', 'Hello World', 'x', 'x', 'hash-en'],
            ['x', 'Bonjour le monde', 'x', 'x', 'hash-fr'],
        ];
    }

    public function testFindsTheMatchingValueByHash(): void
    {
        $this->assertSame('Hello World', ArrayHelper::searchMultiArray('hash-en', $this->sampleTranslations()));
    }

    public function testReturnsFirstMatchWhenHashAppearsMoreThanOnce(): void
    {
        $rows = [
            ['x', 'first match', 'x', 'x', 'dup-hash'],
            ['x', 'second match', 'x', 'x', 'dup-hash'],
        ];

        $this->assertSame('first match', ArrayHelper::searchMultiArray('dup-hash', $rows));
    }

    public function testReturnsNullWhenHashIsNotFound(): void
    {
        $this->assertNull(ArrayHelper::searchMultiArray('does-not-exist', $this->sampleTranslations()));
    }

    public function testReturnsNullForEmptyArray(): void
    {
        $this->assertNull(ArrayHelper::searchMultiArray('hash-en', []));
    }

    public function testSkipsRowsThatAreMissingTheHashIndexInsteadOfErroring(): void
    {
        $rows = [
            ['x'], // malformed row, no index 4 at all
            ['x', 'valid match', 'x', 'x', 'hash-ok'],
        ];

        $this->assertSame('valid match', ArrayHelper::searchMultiArray('hash-ok', $rows));
    }

    public function testFilterMissingMandatoryFieldsReturnsOnlyTheMissingOnes(): void
    {
        $mandatory = [
            76 => 'Password',
            79 => 'Email',
            81 => 'Username',
        ];
        $submitted = [79, 81]; // email + username were selected, password was not

        $this->assertSame(
            [76 => 'Password'],
            ArrayHelper::filterMissingMandatoryFields($mandatory, $submitted)
        );
    }

    public function testFilterMissingMandatoryFieldsReturnsEmptyArrayWhenNothingIsMissing(): void
    {
        $mandatory = [76 => 'Password', 79 => 'Email'];
        $submitted = [76, 79, 81]; // all mandatory fields present, plus an extra optional one

        $this->assertSame([], ArrayHelper::filterMissingMandatoryFields($mandatory, $submitted));
    }

    public function testFilterMissingMandatoryFieldsReturnsAllOfThemWhenNoneWereSubmitted(): void
    {
        $mandatory = [76 => 'Password', 79 => 'Email'];

        $this->assertSame($mandatory, ArrayHelper::filterMissingMandatoryFields($mandatory, []));
    }

    public function testFilterMissingMandatoryFieldsReturnsEmptyArrayWhenNoFieldsAreMandatory(): void
    {
        $this->assertSame([], ArrayHelper::filterMissingMandatoryFields([], [1, 2, 3]));
    }

    public function testArrayKeysMultiReturnsEmptyArrayForEmptyInput(): void
    {
        $this->assertSame([], ArrayHelper::arrayKeysMulti([]));
    }

    public function testArrayKeysMultiFiltersOutNumericKeysFromAFlatArray(): void
    {
        // a plain list - only numeric (list-index) keys, all of which must be filtered out
        $this->assertSame([], ArrayHelper::arrayKeysMulti(['a', 'b', 'c']));
    }

    public function testArrayKeysMultiKeepsStringKeysFromAFlatArray(): void
    {
        $this->assertSame(['foo', 'bar'], ArrayHelper::arrayKeysMulti(['foo' => 1, 'bar' => 2]));
    }

    public function testArrayKeysMultiRecursesIntoNestedArraysAndKeepsInsertionOrder(): void
    {
        $array = [
            'user1' => ['x', 'y'],
            'user2' => ['z'],
        ];

        $this->assertSame(['user1', 'user2'], ArrayHelper::arrayKeysMulti($array));
    }

    public function testArrayKeysMultiRecursesThroughMultipleLevelsAndMixedKeyTypes(): void
    {
        // mirrors the shape of the session's failed-login-attempts array this was written for:
        // string keys (usernames/emails) nested arbitrarily deep, mixed with numeric list keys
        $array = [
            'a' => ['b' => ['c' => 1, 5 => 2]],
            'd' => 3,
        ];

        $this->assertSame(['a', 'b', 'c', 'd'], ArrayHelper::arrayKeysMulti($array));
    }
}
