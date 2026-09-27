<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;

class AuthenticatePanel extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        // Logging out grants no capability. Revocation must not trap a user in a session.
        if ($request->routeIs('filament.platform.auth.logout', 'filament.company.auth.logout')
            && Filament::auth()->check()) {
            $this->auth->shouldUse(Filament::getAuthGuard());

            return;
        }

        parent::authenticate($request, $guards);
    }
}
