<?php

namespace App\Providers\Filament;

use App\Filament\Platform\Resources\Companies\CompanyResource;
use App\Http\Middleware\AuthenticatePanel;
use App\Http\Middleware\SetPlatformContext;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PlatformPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('platform')
            ->path('platform')
            ->brandName('Wastee Platform')
            ->login()
            ->passwordReset()
            ->authGuard('web')
            ->colors(['primary' => Color::Indigo])
            ->pages([Dashboard::class])
            ->resources([CompanyResource::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([AuthenticatePanel::class, SetPlatformContext::class], isPersistent: true);
    }
}
