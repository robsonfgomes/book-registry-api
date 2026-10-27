<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Books\Actions\CreateBook;
use App\Domain\Books\Actions\UpdateBook;
use App\Domain\Books\Models\Book;
use App\Domain\Books\ValueObjects\Isbn;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BookController extends Controller
{
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $books = $request->user()->books()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return BookResource::collection($books);
    }

    public function store(StoreBookRequest $request, CreateBook $createBook): JsonResponse
    {
        $book = $createBook->handle(
            $request->user(),
            $request->validated('name'),
            new Isbn($request->validated('isbn')),
            (string) $request->validated('value'),
        );

        return BookResource::make($book)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Book $book): BookResource
    {
        Gate::authorize('view', $book);

        return BookResource::make($book);
    }

    public function update(UpdateBookRequest $request, Book $book, UpdateBook $updateBook): BookResource
    {
        Gate::authorize('update', $book);

        // Fields missing from a PATCH keep their current values.
        $book = $updateBook->handle(
            $book,
            $request->validated('name', $book->name),
            new Isbn($request->validated('isbn', $book->isbn->value)),
            (string) $request->validated('value', $book->value),
        );

        return BookResource::make($book);
    }

    public function destroy(Book $book): Response
    {
        Gate::authorize('delete', $book);

        $book->delete();

        return response()->noContent();
    }
}
