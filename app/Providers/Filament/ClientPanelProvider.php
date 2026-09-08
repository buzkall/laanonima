<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Auth\Register;
use App\Http\Middleware\ForgetIntendedUrlFromOtherPanels;
use Arzcode\FilamentMagicLogin\MagicLoginPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('client')
            ->path('client')
            ->login(Login::class)
            ->registration(Register::class)
            ->profile(EditProfile::class, isSimple: false)
            ->colors([
                'primary' => Color::Amber,
            ])
            // Resolved lazily: the panel is configured while the application
            // boots, before the Vite manifest is guaranteed to be readable.
            ->brandLogo(fn(): string => Vite::asset('resources/images/brand/la-anonima-logo.png'))
            ->darkModeBrandLogo(fn(): string => Vite::asset('resources/images/brand/la-anonima-logo-dark.png'))
            ->brandLogoHeight('2rem')
            // The same mark the shop's own pages wear; Filament renders a
            // single `rel="icon"`, so the .ico fallback has no place here.
            ->favicon(asset('favicon.svg'))
            ->discoverResources(in: app_path('Filament/Client/Resources'), for: 'App\Filament\Client\Resources')
            ->discoverPages(in: app_path('Filament/Client/Pages'), for: 'App\Filament\Client\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Client/Widgets'), for: 'App\Filament\Client\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                ForgetIntendedUrlFromOtherPanels::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->topbar()
            ->plugin(MagicLoginPlugin::make());
    }
}
