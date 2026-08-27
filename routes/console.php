<?php

use Arzcode\FilamentMagicLogin\Models\MagicLoginToken;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', [
    '--model' => [MagicLoginToken::class],
])->daily();
