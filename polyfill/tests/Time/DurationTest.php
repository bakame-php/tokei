<?php

declare(strict_types=1);

namespace Tests\Time;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Throwable;
use Time\Duration;
use Time\TimeException;
use ValueError;

use const PHP_INT_MAX;

#[Group('time-polyfill')]
final class DurationTest extends TestCase
{
    public function testFromSeconds(): void
    {
        $duration = Duration::fromSeconds(12, 345);

        self::assertSame(12, $duration->seconds);
        self::assertSame(345, $duration->nanoseconds);
        self::assertFalse($duration->negative);
    }

    /**
     * @param class-string<Throwable> $exceptionClassname
     */
    #[TestWith([-1, ValueError::class], 'negative')]
    #[TestWith([9_223_372_036, TimeException::class], 'too large')]
    public function testFromSecondsRejectsInvalidSeconds(int $seconds, string $exceptionClassname): void
    {
        $this->expectException($exceptionClassname);

        Duration::fromSeconds($seconds);
    }

    /**
     * @param class-string<Throwable> $exceptionClassname
     */
    #[TestWith([-1, ValueError::class], 'negative')]
    #[TestWith([1_000_000_000, TimeException::class], 'too large')]
    public function testFromSecondsRejectsInvalidNanoseconds(int $nanoseconds, string $exceptionClassname): void
    {
        $this->expectException($exceptionClassname);

        Duration::fromSeconds(1, $nanoseconds);
    }

    public function testFromNanoseconds(): void
    {
        $duration = Duration::fromNanoseconds(3_500_000_123);

        self::assertSame(3, $duration->seconds);
        self::assertSame(500_000_123, $duration->nanoseconds);
    }

    public function testFromMicroseconds(): void
    {
        $duration = Duration::fromMicroseconds(2_500_001);

        self::assertSame(2, $duration->seconds);
        self::assertSame(500_001_000, $duration->nanoseconds);
    }

    public function testFromMilliseconds(): void
    {
        $duration = Duration::fromMilliseconds(1_500);

        self::assertSame(1, $duration->seconds);
        self::assertSame(500_000_000, $duration->nanoseconds);
    }

    public function testFromMinutes(): void
    {
        self::assertSame(120, Duration::fromMinutes(2)->seconds);
    }

    public function testFromHours(): void
    {
        self::assertSame(7200, Duration::fromHours(2)->seconds);
    }

    public function testItRejectOverflowInteger(): void
    {
        $this->expectException(TimeException::class);

        Duration::fromMicroseconds(PHP_INT_MAX);
    }

    public function testItRejectInvalidIntegerValue(): void
    {
        $this->expectException(TimeException::class);

        Duration::fromMicroseconds(9_223_372_036_000_000);
    }

    #[DataProvider('validIso8601Provider')]
    public function testParseIso8601(
        string $spec,
        int $seconds,
        int $nanoseconds,
        bool $negative
    ): void {
        $duration = Duration::fromIso8601String($spec);

        self::assertSame($seconds, $duration->seconds);
        self::assertSame($nanoseconds, $duration->nanoseconds);
        self::assertSame($negative, $duration->negative);
    }

    /**
     * @return iterable<array{0: non-empty-string, non-negative-int, non-negative-int, bool}>
     */
    public static function validIso8601Provider(): iterable
    {
        yield ['PT1S', 1, 0, false];
        yield ['PT1.5S', 1, 500_000_000, false];
        yield ['PT1,5S', 1, 500_000_000, false];
        yield ['PT2M30S', 150, 0, false];
        yield ['PT1H2M3.123456789S', 3723, 123_456_789, false];
        yield ['-PT5S', 5, 0, true];
        yield ['-PT0S', 0, 0, false];
    }

    #[DataProvider('invalidIso8601Provider')]
    public function testRejectInvalidIso8601(string $spec): void
    {
        $this->expectException(TimeException::class);

        Duration::fromIso8601String($spec);
    }

    /**
     * @return iterable<array{0: non-empty-string}>
     */
    public static function invalidIso8601Provider(): iterable
    {
        yield ['P1D'];
        yield ['PT'];
        yield ['P'];
        yield ['foo'];
        yield ['PT1Y'];
    }

    public function testNegatePositiveDuration(): void
    {
        $duration = Duration::fromSeconds(10)->negate();

        self::assertTrue($duration->negative);
        self::assertSame(10, $duration->seconds);
    }

    public function testNegatingZeroReturnsSameInstance(): void
    {
        $duration = Duration::fromSeconds(0);

        self::assertSame($duration, $duration->negate());
    }

    public function testAddition(): void
    {
        $result = Duration::fromSeconds(2, 900_000_000)
            ->add(Duration::fromSeconds(1, 200_000_000));

        self::assertSame(4, $result->seconds);
        self::assertSame(100_000_000, $result->nanoseconds);
    }

    public function testAdditionWithNegativeOperand(): void
    {
        $a = Duration::fromSeconds(10);
        $b = Duration::fromSeconds(3)->negate();

        $result = $a->add($b);

        self::assertSame(7, $result->seconds);
        self::assertFalse($result->negative);
    }

    public function testSubtraction(): void
    {
        $result = Duration::fromSeconds(10)
            ->sub(Duration::fromSeconds(3));

        self::assertSame(7, $result->seconds);
        self::assertFalse($result->negative);
    }

    public function testSubtractionBorrow(): void
    {
        $result = Duration::fromSeconds(5)
            ->sub(Duration::fromSeconds(2, 500_000_000));

        self::assertSame(2, $result->seconds);
        self::assertSame(500_000_000, $result->nanoseconds);
    }

    public function testSubtractionProducesNegativeDuration(): void
    {
        $result = Duration::fromSeconds(2)
            ->sub(Duration::fromSeconds(5));

        self::assertSame(3, $result->seconds);
        self::assertTrue($result->negative);
    }

    public function testMultiplyBy(): void
    {
        $result = Duration::fromSeconds(1, 600_000_000)
            ->multiplyBy(3);

        self::assertSame(4, $result->seconds);
        self::assertSame(800_000_000, $result->nanoseconds);
    }

    public function testMultiplyByRejectsNegativeFactor(): void
    {
        $this->expectException(ValueError::class);

        Duration::fromSeconds(1)->multiplyBy(-1);
    }

    public function testDivideBy(): void
    {
        $result = Duration::fromSeconds(5)
            ->divideBy(2);

        self::assertSame(2, $result->seconds);
        self::assertSame(500_000_000, $result->nanoseconds);
    }

    public function testDivideByTruncatesFractionalNanoseconds(): void
    {
        $result = Duration::fromSeconds(0, 5)
            ->divideBy(2);

        self::assertSame(0, $result->seconds);
        self::assertSame(2, $result->nanoseconds);
    }

    #[TestWith([0], 'division with zero')]
    #[TestWith([-1], 'division with negative factor')]
    public function testDivideByRejectsInvalidDivisor(int $divisor): void
    {
        $this->expectException(ValueError::class);

        Duration::fromSeconds(1)->divideBy($divisor);
    }

    public function testCompare(): void
    {
        self::assertSame(
            0,
            Duration::compare(
                Duration::fromSeconds(1),
                Duration::fromSeconds(1)
            )
        );

        self::assertSame(
            -1,
            Duration::compare(
                Duration::fromSeconds(1),
                Duration::fromSeconds(2)
            )
        );

        self::assertSame(
            1,
            Duration::compare(
                Duration::fromSeconds(2),
                Duration::fromSeconds(1)
            )
        );
    }

    public function testCompareNegativeDurations(): void
    {
        self::assertSame(
            -1,
            Duration::compare(
                Duration::fromSeconds(2)->negate(),
                Duration::fromSeconds(1)->negate()
            )
        );

        self::assertSame(
            -1,
            Duration::compare(
                Duration::fromSeconds(1)->negate(),
                Duration::fromSeconds(1)
            )
        );
    }

    #[DataProvider('invalidIso8601Durations')]
    public function test_it_rejects_invalid_iso8601_durations(string $specification): void
    {
        $this->expectException(TimeException::class);

        Duration::fromIso8601String($specification);
    }

    /**
     * @return iterable<non-empty-string, array{0: string}>
     */
    public static function invalidIso8601Durations(): iterable
    {
        yield 'empty string' => [''];

        yield 'invalid prefix' => ['1H'];

        yield 'missing time designator' => ['P1H'];

        yield 'missing value' => ['PT'];

        yield 'unsupported years component' => ['P1Y'];

        yield 'unsupported months component' => ['P1M'];

        yield 'unsupported weeks component' => ['P1W'];

        yield 'unsupported days component' => ['P1D'];

        yield 'invalid fraction format' => ['PT1.S'];

        yield 'invalid unit' => ['PT1X'];

        yield 'invalid duplicated seconds' => ['PT1S2S'];

        yield 'invalid duplicated fraction' => ['PT1.5.5S'];

        yield 'hours overflow integer range' => ['PT2562047789H'];

        yield 'combined components overflow integer range' => ['PT2562047788H59M59S'];

        yield 'combined components exceeds integer range' => ['PT2562047788015215H59M59S'];
    }
}
