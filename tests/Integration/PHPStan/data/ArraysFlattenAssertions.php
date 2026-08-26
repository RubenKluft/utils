<?php

declare(strict_types=1);

namespace DR\Utils\Tests\Integration\PHPStan\data;

use DR\Utils\Arrays;
use stdClass;

use function PHPStan\Testing\assertType;

class ArraysFlattenAssertions
{
    public function assertions(): void
    {
        // assert empty array stays empty
        assertType('array{}', Arrays::flatten([]));
        assertType('array{}', Arrays::flatten([[], []]));

        // assert flat literal array is unaffected (values converted to a 0-indexed list)
        assertType("array{1, 2, 3}", Arrays::flatten([1, 2, 3]));
        assertType("array{'a', 1, true}", Arrays::flatten(['foo' => 'a', 'bar' => 1, 'baz' => true]));

        // assert nested literal arrays are recursively flattened
        assertType("array{'a', 'b', 'c', 'd'}", Arrays::flatten(['a', ['b', ['c', 'd']]]));
        assertType("array{1, 'a', true, null}", Arrays::flatten([1, ['a', [true, [null]]]]));

        // assert mixed literal/object leafs
        assertType('array{stdClass, 1}', Arrays::flatten([new stdClass(), [1]]));

        // assert dynamic single-level array
        /** @var array<string, int> $data */
        $data = ['foo' => 1, 'bar' => 2];
        assertType('array<int, int>', Arrays::flatten($data));

        // assert dynamic nested array
        /** @var array<string, array<int, int>> $data */
        $data = ['foo' => [1, 2], 'bar' => [3]];
        assertType('array<int, int>', Arrays::flatten($data));

        // assert dynamic array with mixed leaf types
        /** @var array<int|string, string|array<int, bool>> $data */
        $data = ['foo', [true, false]];
        assertType('array<int, bool|string>', Arrays::flatten($data));
    }
}
