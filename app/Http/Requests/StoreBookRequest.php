<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Books\Exceptions\DuplicateIsbnException;
use App\Domain\Books\Models\Book;
use App\Domain\Books\ValueObjects\Isbn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
