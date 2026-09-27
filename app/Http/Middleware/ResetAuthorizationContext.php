<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

class ResetAuthorizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(AuthorizationContext::class);
        $context->reset();
        Livewire::flushState();
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);

        try {
            return $next($request);
        } finally {
            $user = $request->user();
            $context->reset($user instanceof User ? $user : null);
            Livewire::flushState();
            Filament::setTenant(null, isQuiet: true);
            Filament::setCurrentPanel(null);
            $request->attributes->remove('wastee.authorization_scope');
        }
    }
}
