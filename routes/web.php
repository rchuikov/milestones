<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\ProfileController;
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

    Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])
        ->name('auth.google');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])
        ->name('auth.google.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');

    Route::get('/email/verify', [AuthController::class, 'showVerifyEmail'])
        ->name('verification.notice');
    Route::post('/email/verification-resend', [AuthController::class, 'resendVerificationEmail'])
        ->name('verification.resend');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [MilestoneController::class, 'index'])
        ->name('milestones.index');
    Route::resource('milestones', MilestoneController::class)
        ->except(['index', 'show']);

    Route::post('milestones/{milestone}/start', [MilestoneController::class, 'start'])
        ->name('milestones.start');
    Route::post('milestones/{milestone}/stop', [MilestoneController::class, 'stop'])
        ->name('milestones.stop');
    Route::post('milestones/{milestone}/reset', [MilestoneController::class, 'reset'])
        ->name('milestones.reset');

    Route::get('/milestones/{milestone}/history', [MilestoneController::class, 'history'])
        ->name('milestones.history');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
});
