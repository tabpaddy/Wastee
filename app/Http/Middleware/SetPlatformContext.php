<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPlatformContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $context = app(AuthorizationContext::class);
        if ($request->routeIs('filament.platform.auth.logout')) {
            $context->reset($user);

            return $next($request);
        }
        $context->setPlatform($user);
        abort_unless($context->allowsPlatform($user, 'platform.access'), 403);
        $context->bindRequestScope('platform:'.$user->getKey());

        return $next($request);
    }
}
