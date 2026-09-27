<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\LoginOwnerRequest;
use App\Http\Requests\Onboarding\RegisterOwnerRequest;
use App\Http\Requests\Onboarding\VerifyOwnerEmailRequest;
use App\Services\Onboarding\OwnerRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class OwnerAuthController extends Controller
{
    public function register(RegisterOwnerRequest $request, OwnerRegistrationService $service): RedirectResponse
    {
        $user = $service->register($request->validated());
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function login(LoginOwnerRequest $request): RedirectResponse
    {
        if (! Auth::attempt([...$request->validated(), 'status' => 'active'])) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match an active account.']);
        }
        $request->session()->regenerate();

        return redirect()->route('onboarding.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function verify(VerifyOwnerEmailRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('onboarding.index')->with('status', 'Email verified.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'If verification is needed, a fresh link has been sent.');
    }
}
