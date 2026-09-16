<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])
        ->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->name('login.submit');

    Route::get('/register', [AuthController::class, 'showRegister'])
        ->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->name('register.submit');

    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])
        ->name('forgot-password');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->name('forgot-password.submit');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])
        ->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->name('reset-password.submit');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');

    Route::get('/email/verify', [AuthController::class, 'showVerifyEmail'])
        ->name('verification.notice');
    Route::post('/email/verification-resend', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1')
        ->name('verification.resend');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', fn () => view('home'));
});
