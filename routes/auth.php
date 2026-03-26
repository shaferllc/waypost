<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::livewire('register', 'pages.auth.register')
        ->name('register');

    Route::livewire('login', 'pages.auth.login')
        ->name('login');

    Route::livewire('login/email-code', 'pages.auth.login-email-code')
        ->name('login.email-code');

    // Magic link callback: Fleet\IdpClient\routes\email-sign-in.php (login.email-magic).
    // Forgot / reset password: shaferllc/fleet-idp-client routes/account.php (IdP redirect or local broker).
});

Route::middleware(['guest', 'two_factor.challenge'])->group(function (): void {
    Route::livewire('two-factor-challenge', 'pages.auth.two-factor-challenge')
        ->name('two-factor.challenge');
});

Route::middleware('auth')->group(function () {
    Route::livewire('verify-email', 'pages.auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::livewire('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');
});
