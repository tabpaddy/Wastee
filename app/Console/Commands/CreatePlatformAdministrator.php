<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use App\Services\Auth\RoleProvisioner;
use App\Support\PermissionCatalogue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class CreatePlatformAdministrator extends Command
{
    protected $signature = 'wastee:make-platform-admin {--email=} {--first-name=} {--last-name=}';

    protected $description = 'Create a new Platform Super Admin using an interactively supplied password';

    public function handle(RoleProvisioner $roles, AuthorizationContext $context): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run interactively: passwords must be entered through the hidden prompt.');

            return self::FAILURE;
        }

        $attributes = [
            'email' => mb_strtolower(trim($this->option('email') ?: $this->ask('Email'))),
            'first_name' => trim($this->option('first-name') ?: $this->ask('First name')),
            'last_name' => trim($this->option('last-name') ?: $this->ask('Last name')),
        ];
        $identity = Validator::make($attributes, [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
        ]);

        if ($identity->fails()) {
            $this->error($identity->errors()->first());

            return self::FAILURE;
        }

        $password = $this->secret('Password');
        $confirmation = $this->secret('Confirm password');
        $credentials = Validator::make(['password' => $password, 'password_confirmation' => $confirmation], [
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($credentials->fails()) {
            $this->error($credentials->errors()->first());

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($attributes, $password, $roles, $context): void {
                $roles->seedPlatform();
                $user = User::create($attributes + ['name' => $attributes['first_name'].' '.$attributes['last_name'], 'password' => $password]);
                $context->runForPlatform($user, function () use ($user): void {
                    $role = Role::query()->where('company_id', 0)->where('name', PermissionCatalogue::PLATFORM_SUPER_ADMIN)->where('guard_name', 'web')->sole();
                    $user->assignRole($role);
                });
            });
        } finally {
            $context->reset();
        }

        $this->info('Platform administrator created. Sign in at /platform/login.');

        return self::SUCCESS;
    }
}
