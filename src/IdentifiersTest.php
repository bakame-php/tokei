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
        self::assertSame('John', $attributes->first());
        self::assertSame('5', $attributes->last());
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
}
