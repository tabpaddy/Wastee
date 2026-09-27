<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Company || (is_string($tenant) && filled($tenant)), 403);
        $context = app(AuthorizationContext::class);
        $context->setCompany($user, $tenant);
        abort_unless($context->allowsCompany($user, 'company.access'), 403);
        $context->bindRequestScope('company:'.$context->companyId().':'.$user->getKey());

        return $next($request);
    }
}
