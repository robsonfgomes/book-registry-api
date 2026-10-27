<?php

declare(strict_types=1);

namespace App\Domain\Books\ValueObjects;

use InvalidArgumentException;

final readonly class Isbn
{
    public const int LENGTH = 13;

    public function __construct(public string $value)
    {
        if (strlen($value) !== self::LENGTH || ! ctype_digit($value)) {
            throw new InvalidArgumentException(
                sprintf('Invalid ISBN [%s]: it must contain exactly %d digits.', $value, self::LENGTH),
            );
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
