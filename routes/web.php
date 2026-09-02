<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\BookRequestController;
use Illuminate\Support\Facades\Route;

// Optional local-only helper. It may be a symlink into a path the running process is not allowed to read
// (a Git GUI or other sandboxed app booting Artisan), so failing to open it must never take the whole app down.
if (is_readable(__DIR__ . '/autologin.php')) {
    @include __DIR__ . '/autologin.php';
}

Route::controller(BookController::class)->group(function(): void {
    Route::get('/', 'index')->name('home');

    Route::get('/estanteria', 'shelf')->name('books.shelf');

    Route::get('/libro/{book}', 'show')->name('books.show');

    Route::get('/autor/{author}', 'author')->name('authors.show');
    Route::get('/editorial/{publisher}', 'publisher')->name('publishers.show');
});

Route::middleware('auth')->controller(BookRequestController::class)->group(function(): void {
    Route::get('/pedir-libro', 'create')->name('book-requests.create');
    Route::post('/pedir-libro', 'store')->name('book-requests.store');
    Route::get('/libro/{book}/pedir', 'create')->name('book-requests.create.book');
});
