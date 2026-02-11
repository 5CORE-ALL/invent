<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\PermissionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\RoleController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\Auth\UserProfileController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\NewPermissionController;
use App\Models\NewPermission;
use Illuminate\Support\Facades\Route;

Route::get('/register', [RegisteredUserController::class, 'create'])
    ->middleware('guest')
    ->name('register');

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('guest');

Route::get('/login', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('login');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest');

Route::middleware('guest')->controller(SocialiteController::class)->group(function () {
    Route::get('/google', 'googlelogin')->name('auth.google');
    Route::get('/google/callback', 'googleAuthentication')->name('auth.google-callback');
});

Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
    ->middleware('guest')
    ->name('password.request');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('guest')
    ->name('password.email');

Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
    ->middleware('guest')
    ->name('password.reset');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest')
    ->name('password.update');

Route::get('/verify-email', [EmailVerificationPromptController::class, '__invoke'])
    ->middleware('auth')
    ->name('verification.notice');

Route::get('/verify-email/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

Route::get('/confirm-password', [ConfirmablePasswordController::class, 'show'])
    ->middleware('auth')
    ->name('password.confirm');


Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
// Route::get('/api/permissions', [PermissionController::class, 'getPermissions']);

Route::post('/confirm-password', [ConfirmablePasswordController::class, 'store'])
    ->middleware('auth');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/profile', [UserProfileController::class, 'profileView'])->middleware('auth')->name('profile');
Route::put('/profile', [UserProfileController::class, 'updateProfile'])->middleware('auth')->name('profile.update');
// Update password
Route::put('/profile/password', [UserProfileController::class, 'updatePassword'])->middleware('auth')->name('password.update');

// Route::get('/permissions', [PermissionController::class, 'index'])->middleware('auth')->name('permissions');
// Add these specific routes ABOVE any catch-all routes

Route::get('users/{user}/permissions', [PermissionController::class, 'getUserPermissionData'])
    ->name('users.permissions.get');

Route::post('users/{user}/permissions', [PermissionController::class, 'updatePermissions'])
    ->name('users.permissions.update');

Route::get('/roles', [RoleController::class, 'index'])
    ->middleware(['auth', 'isAdmin'])
    ->name('roles');

Route::put('/roles/{user}', [RoleController::class, 'update'])
    ->middleware(['auth', 'isAdmin'])
    ->name('roles.update');

Route::post('/save-column-permission', [PermissionController::class, 'saveColumnPermission'])->middleware('auth');


Route::get('/permissions', [NewPermissionController::class, 'index'])->name('permissions');
Route::post('/permissions/store', [NewPermissionController::class, 'store'])->name('permissions.store');

Route::get('/permissions/edit', [NewPermissionController::class, 'edit'])->name('permissions.edit');

Route::get('/permissions/view', [NewPermissionController::class, 'view'])->name('permissions.view');