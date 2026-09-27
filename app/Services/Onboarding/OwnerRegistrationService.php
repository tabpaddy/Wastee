<?php

namespace App\Services\Onboarding;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class OwnerRegistrationService
{
    public static function rules(): array
    {
        return ['first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'phone' => ['required', 'string', 'max:40'],
            'password_confirmation' => ['required', 'string'], 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]];
    }

    public function register(array $input): User
    {
        $input['email'] = strtolower(trim($input['email'] ?? ''));
        $data = Validator::make($input, self::rules())->validate();
        unset($data['password_confirmation']);
        $user = DB::transaction(fn () => User::create([...$data, 'name' => $data['first_name'].' '.$data['last_name']]));
        // Delivery failure must not discard the account; the owner can resend verification.
        try {
            event(new Registered($user));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $user;
    }
}
