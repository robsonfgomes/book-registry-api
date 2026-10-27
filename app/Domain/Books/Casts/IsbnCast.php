<?php

declare(strict_types=1);

namespace App\Domain\Books\Casts;

use App\Domain\Books\ValueObjects\Isbn;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<Isbn, Isbn>
 */
class IsbnCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Isbn
    {
        return $value === null ? null : new Isbn($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Isbn) {
            throw new InvalidArgumentException('The isbn attribute must be an instance of '.Isbn::class.'.');
        }

        return $value->value;
    }
}
