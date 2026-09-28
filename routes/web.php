<?php

use App\Http\Controllers\Onboarding\CompanyDocumentController;
use App\Http\Controllers\Onboarding\CompanyOnboardingController;
use App\Http\Controllers\Onboarding\OwnerAuthController;
use App\Http\Controllers\StaffInvitationController;
use App\Http\Middleware\SetPlatformContext;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::middleware('guest')->group(function (): void {
    Route::view('/register', 'onboarding.register')->name('register');
    Route::post('/register', [OwnerAuthController::class, 'register'])->middleware('throttle:6,1');
    Route::view('/login', 'onboarding.login')->name('login');
    Route::post('/login', [OwnerAuthController::class, 'login'])->middleware('throttle:6,1');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [OwnerAuthController::class, 'logout'])->name('logout');
    Route::view('/email/verify', 'onboarding.verify')->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [OwnerAuthController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->whereUuid('id')->name('verification.verify');
    Route::post('/email/verification-notification', [OwnerAuthController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');

    Route::middleware('verified')->prefix('onboarding')->name('onboarding.')->group(function (): void {
        Route::get('/', [CompanyOnboardingController::class, 'index'])->name('index');
        Route::post('/companies', [CompanyOnboardingController::class, 'store'])->name('store');
        Route::get('/{company:uuid}', [CompanyOnboardingController::class, 'show'])->whereUuid('company')->name('show');
        Route::put('/{company:uuid}', [CompanyOnboardingController::class, 'update'])->whereUuid('company')->name('update');
        Route::post('/{company:uuid}/locations', [CompanyOnboardingController::class, 'location'])->whereUuid('company')->name('location');
        Route::post('/{company:uuid}/documents', [CompanyOnboardingController::class, 'document'])->whereUuid('company')->name('document');
        Route::post('/{company:uuid}/submit', [CompanyOnboardingController::class, 'submit'])->whereUuid('company')->name('submit');
    });
    Route::get('/onboarding/{company:uuid}/documents/{document:uuid}', CompanyDocumentController::class)
        ->whereUuid(['company', 'document'])->scopeBindings()->name('onboarding.download');
    Route::get('/platform-review/{company:uuid}/documents/{document:uuid}', CompanyDocumentController::class)
        ->middleware(SetPlatformContext::class)->whereUuid(['company', 'document'])->scopeBindings()->name('platform.documents.download');
});

Route::prefix('staff-invitations')->name('staff-invitations.')->middleware('throttle:30,1')->group(function (): void {
    Route::get('/{invitation:uuid}/{token}', [StaffInvitationController::class, 'open'])
        ->whereUuid('invitation')->where('token', '[A-Za-z0-9]{64}')->name('open');
    Route::get('/{invitation:uuid}', [StaffInvitationController::class, 'show'])->whereUuid('invitation')->name('show');
    Route::post('/{invitation:uuid}', [StaffInvitationController::class, 'accept'])->whereUuid('invitation')->name('accept');
});
