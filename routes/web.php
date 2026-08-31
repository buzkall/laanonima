<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

if (file_exists(__DIR__ . '/autologin.php')) {
    require __DIR__ . '/autologin.php';
}

Route::get('/', [BookController::class, 'index'])->name('home');

Route::get('/libro/{book}', [BookController::class, 'show'])->name('books.show');

Route::get('/autor/{author}', [BookController::class, 'author'])->name('authors.show');
Route::get('/editorial/{publisher}', [BookController::class, 'publisher'])->name('publishers.show');
