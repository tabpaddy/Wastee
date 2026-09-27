<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Services\Auth\AuthorizationContext;
use App\Support\PermissionCatalogue;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AuthorizationContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::createUrlUsing(fn (User $user) => URL::temporarySignedRoute('verification.verify', now()->addMinutes(60),
            ['id' => $user->uuid, 'hash' => sha1($user->getEmailForVerification())]));
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        Gate::before(function (User $user, string $ability): ?bool {
            $context = app(AuthorizationContext::class);

            if (str_starts_with($ability, 'platform.')) {
                return $context->allowsPlatform($user, $ability);
            }

            if (in_array($ability, PermissionCatalogue::company(), true)) {
                return $context->allowsCompany($user, $ability);
            }

            return null;
        });

        // Resolve the scoped service at event time; never capture a request-scoped
        // instance in worker listeners. Jobs opt in with runForCompany/runForPlatform.
        $reset = fn () => app(AuthorizationContext::class)->reset();
        Queue::before($reset);
        Queue::after($reset);
        Queue::exceptionOccurred($reset);
        Queue::looping($reset);
    }
}
