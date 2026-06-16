<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

use function serialize;
use function unserialize;

#[CoversClass(Identifiers::class)]
#[CoversClass(TemporalException::class)]
final class IdentifiersTest extends TestCase
{
    public function testEmptyAttributes(): void
    {
        $attributes = new Identifiers();

        self::assertTrue($attributes->isEmpty());
        self::assertCount(0, $attributes);
    }

    public function testStoresValues(): void
    {
        $attributes = new Identifiers(['John', '5']);

        self::assertSame(['John', '5'], $attributes->all());
        self::assertSame('John', $attributes->primary());
        self::assertSame('5', $attributes->nth(-1));
    }

    public function testHas(): void
    {
        $attributes = new Identifiers(['John', '5']);

        self::assertTrue($attributes->has('John'));
        self::assertFalse($attributes->has('Jane'));
    }

    public function testGetThrowsForUnknownKey(): void
    {
        $attributes = new Identifiers();

        self::assertSame([], $attributes->all());

        $this->expectException(TokeiException::class);

        $attributes->get(1);
    }

    public function testEquals(): void
    {
        $a = new Identifiers(['John']);
        $b = new Identifiers(['John']);
        $c = new Identifiers(['Jane']);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testMergeWithoutArgumentsReturnsSameInstance(): void
    {
        $attributes = new Identifiers(['John']);

        self::assertTrue($attributes->equals($attributes->merge($attributes)));
    }

    public function testOnlyAndExceptMethods(): void
    {
        $attributes = new Identifiers([
            'employee',
            'priority',
            'age',
            'gender',
            'height',
        ]);

        $except = $attributes->except('age', 'gender');
        $only = $attributes->only('age', 'gender');

        self::assertFalse($except->equals($only));
        self::assertTrue($only->has('age'));
        self::assertFalse($except->has('age'));
    }

    public function testALl(): void
    {
        $identifiers = new Identifiers([
            'employee',
            'priority',
            'age',
            'gender',
            'height',
        ]);

        $attr = $identifiers->all();
        self::assertCount(5, $attr);
        self::assertSame('employee', $attr[0]);
    }

    public function testIteratorYieldsKeyValuePairs(): void
    {
        $attrs = new Identifiers([
            'employee',
            'tag',
        ]);

        $pairs = [];
        foreach ($attrs as $value) {
            $pairs[] = $value;
        }

        self::assertSame(['employee', 'tag'], $pairs);
    }

    public function test_wrong_identifier_type(): void
    {
        $this->expectException(TemporalException::class);

        new Identifiers([new stdClass()]); /* @phpstan-ignore-line */
    }

    public function test_using_empty_identifier_type(): void
    {
        $this->expectException(TemporalException::class);

        new Identifiers('   ');
    }

    public function test_using_invalud_identifier(): void
    {
        $this->expectException(TemporalException::class);

        new Identifiers('identifier_with_comma,');
    }

    public function test_duration_can_be_serialized_and_unserialized(): void
    {
        $identifiers = new Identifiers([
            'employee',
            'priority',
            'age',
            'gender',
            'height',
        ]);
        $restored = unserialize(serialize($identifiers));

        self::assertInstanceOf(Identifiers::class, $restored);
        self::assertEquals($identifiers, $restored);
    }

    public function test_merge_effect_on_primary_identifiers(): void
    {
        $identifiersA = new Identifiers([
            'employee',
            'priority',
            'age',
        ]);

        $identifiersB = new Identifiers([
            'gender',
            'height',
        ]);

        $mergeAB = $identifiersA->merge($identifiersB);
        $mergeBA = $identifiersB->merge($identifiersA);

        self::assertSame('employee', $mergeAB->primary());
        self::assertSame('gender', $mergeBA->primary());
        self::assertTrue($mergeBA->equals($mergeAB));
        self::assertNotSame($mergeBA->asCommaSeparated(), $mergeAB->asCommaSeparated());
        self::assertSame($mergeBA->sorted()->asCommaSeparated(), $mergeAB->sorted()->asCommaSeparated());
        self::assertNotSame($mergeBA->sorted(), $mergeAB->sorted(Direction::Descending));
    }

    public function test_unique_is_case_sensitive(): void
    {
        $identifiersA = new Identifiers([
            'employee',
            'priority',
            'age',
        ]);

        $identifiersB = new Identifiers([
            'gender',
            'height',
            'Employee',
        ]);

        self::assertTrue(
            $identifiersA
                ->merge($identifiersB)
                ->has('employee', 'Employee')
        );
    }

    public function test_fails_from_comma_separated(): void
    {
        $this->expectException(TemporalException::class);

        Identifiers::fromCommaSeparated('foo,,bar');
    }
}
