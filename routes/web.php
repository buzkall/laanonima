<?php

use Illuminate\Support\Facades\Route;

if (file_exists(__DIR__ . '/autologin.php')) {
    require __DIR__ . '/autologin.php';
}

Route::view('/', 'welcome')->name('home');
