<?php

namespace App\Providers\Filament;

use App\Filament\Company\Resources\Invitations\InvitationResource;
use App\Filament\Company\Resources\Properties\PropertyResource;
use App\Filament\Company\Resources\Residents\ResidentResource;
use App\Filament\Company\Resources\Roles\RoleResource;
use App\Filament\Company\Resources\ServiceAreas\ServiceAreaResource;
use App\Filament\Company\Resources\Staff\StaffResource;
use App\Http\Middleware\AuthenticatePanel;
use App\Http\Middleware\SetCompanyContext;
use App\Models\Company;
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

class CompanyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('company')
            ->path('company')
            ->brandName('Wastee Company')
            ->login()
            ->passwordReset()
            ->authGuard('web')
            ->colors(['primary' => Color::Emerald])
            ->tenant(Company::class, slugAttribute: 'uuid', ownershipRelationship: 'company')
            ->pages([Dashboard::class])
            ->resources([
                ServiceAreaResource::class,
                PropertyResource::class,
                ResidentResource::class,
                StaffResource::class,
                RoleResource::class,
                InvitationResource::class,
            ])
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
            ->authMiddleware([AuthenticatePanel::class], isPersistent: true)
            ->tenantMiddleware([SetCompanyContext::class], isPersistent: true);
    }
}
