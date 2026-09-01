<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\BookRequestController;
use Illuminate\Support\Facades\Route;

if (file_exists(__DIR__ . '/autologin.php')) {
    require __DIR__ . '/autologin.php';
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
