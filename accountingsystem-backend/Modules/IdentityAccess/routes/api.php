<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Routes;

use Illuminate\Support\Facades\Route;
use Modules\IdentityAccess\Http\Controllers\Auth\CurrentUserController;
use Modules\IdentityAccess\Http\Controllers\Auth\LoginController;
use Modules\IdentityAccess\Http\Controllers\Auth\LogoutController;
use Modules\IdentityAccess\Http\Controllers\Auth\RegisterController;
use Modules\IdentityAccess\Http\Controllers\EmailVerification\SendEmailVerificationNotificationController;
use Modules\IdentityAccess\Http\Controllers\EmailVerification\VerifyEmailController;
use Modules\IdentityAccess\Http\Controllers\Password\ResetPasswordController;
use Modules\IdentityAccess\Http\Controllers\Password\SendPasswordResetLinkController;

// These paths and route names intentionally preserve the existing frontend
// contract while ownership moves into the IdentityAccess module.
Route::post('register', RegisterController::class)
    ->middleware('guest')
    ->name('register');

// Deliberately NOT behind the `guest` middleware. `guest` returns a 302
// redirect when a session is already authenticated, which meant a stale-but-
// still-valid session (e.g. one that survived a failed logout or a server
// restart) blocked re-login entirely: the SPA received a redirect instead of
// JSON and login could never regenerate the session. Successful login rotates
// the session; invalid credentials leave the existing session intact.
Route::post('login', LoginController::class)
    ->middleware('throttle:5,1')
    ->name('login');

Route::post('forgot-password', SendPasswordResetLinkController::class)
    ->middleware('guest')
    ->name('password.email');

Route::post('reset-password', ResetPasswordController::class)
    ->middleware('guest')
    ->name('password.store');

Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('email/verification-notification', SendEmailVerificationNotificationController::class)
    ->middleware(['auth:sanctum', 'throttle:6,1'])
    ->name('verification.send');

Route::post('logout', LogoutController::class)
    ->middleware('auth:sanctum')
    ->name('logout');

Route::get('me', CurrentUserController::class)
    ->middleware('auth:sanctum')
    ->name('me');
