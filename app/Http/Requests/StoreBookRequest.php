<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Books\Exceptions\DuplicateIsbnException;
use App\Domain\Books\Models\Book;
use App\Domain\Books\ValueObjects\Isbn;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[BodyParameter(
    'isbn',
    description: 'ISBN-13: exactly 13 digits, sent as a string so leading zeros are kept.',
    example: '9780132350884',
)]
#[BodyParameter(
    'value',
    description: 'Up to 2 decimal places. Returned as a string to keep the exact amount.',
    example: 89.90,
)]
class StoreBookRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'isbn' => [
                'required',
                'string',
                'digits:'.Isbn::LENGTH,
                Rule::unique(Book::class)->where('user_id', $this->user()->id),
            ],
            'value' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.Book::MAX_VALUE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'isbn.unique' => DuplicateIsbnException::MESSAGE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'isbn' => 'ISBN',
        ];
    }
}
