<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Books\ValueObjects;

use App\Domain\Books\ValueObjects\Isbn;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IsbnTest extends TestCase
{
    #[Test]
    public function itAcceptsThirteenDigits(): void
    {
        $isbn = new Isbn('9780132350884');

        $this->assertSame('9780132350884', $isbn->value);
    }

    #[Test]
    public function itKeepsLeadingZeros(): void
    {
        $isbn = new Isbn('0780132350884');

        $this->assertSame('0780132350884', $isbn->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIsbns(): array
    {
        return [
            'letters' => ['97801323508AB'],
            'isbn-10 with 10 digits' => ['0132350882'],
            '12 digits' => ['978013235088'],
            '14 digits' => ['97801323508841'],
            'x as check digit (isbn-10 style)' => ['978013235088X'],
            'hyphens' => ['978-0132350884'],
            'empty' => [''],
        ];
    }

    #[Test]
    #[DataProvider('invalidIsbns')]
    public function itRejectsValuesThatAreNotExactlyThirteenDigits(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('it must contain exactly 13 digits');

        new Isbn($value);
    }

    #[Test]
    public function itEqualsAnotherIsbnWithTheSameValue(): void
    {
        $this->assertTrue((new Isbn('9780132350884'))->equals(new Isbn('9780132350884')));
    }

    #[Test]
    public function itDoesNotEqualAnIsbnWithADifferentValue(): void
    {
        $this->assertFalse((new Isbn('9780132350884'))->equals(new Isbn('9780134757599')));
    }
}
