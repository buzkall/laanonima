<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\ClientPanelProvider;
use Bugsnag\BugsnagLaravel\BugsnagServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ClientPanelProvider::class,
    BugsnagServiceProvider::class,
];
