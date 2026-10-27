<?php

declare(strict_types=1);

namespace App\Domain\Books\Models;

use App\Domain\Books\Casts\IsbnCast;
use App\Domain\Books\ValueObjects\Isbn;
use App\Models\User;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Isbn $isbn
 * @property string $value
 */
#[Fillable(['name', 'isbn', 'value'])]
#[UseFactory(BookFactory::class)]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    // Largest amount the DECIMAL(10,2) "value" column can store.
    public const string MAX_VALUE = '99999999.99';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'isbn' => IsbnCast::class,
            'value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
