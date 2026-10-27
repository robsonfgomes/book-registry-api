<?php

declare(strict_types=1);

namespace App\Domain\Books\Exceptions;

use App\Domain\Books\ValueObjects\Isbn;
use App\Domain\Exceptions\BusinessRuleException;
use Throwable;

class DuplicateIsbnException extends BusinessRuleException
{
    public const string MESSAGE = 'You already have a book with this ISBN.';

    public function __construct(public readonly Isbn $isbn, ?Throwable $previous = null)
    {
        parent::__construct(self::MESSAGE, previous: $previous);
    }

    public function field(): string
    {
        return 'isbn';
    }
}
