<?php

use Arzcode\FilamentMagicLogin\Models\MagicLoginToken;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', [
    '--model' => [MagicLoginToken::class],
])->daily();
