<?php

use App\Http\Controllers\AcademicStructureController;
use App\Http\Controllers\AccountRecoveryController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PlatformAdminController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function (): void {
    Route::get('/session', [AuthController::class, 'session']);
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->middleware(['auth', 'throttle:6,1']);
    Route::post('/forgot-password', [AccountRecoveryController::class, 'request'])->middleware('throttle:6,1');
    Route::post('/reset-password', [AccountRecoveryController::class, 'reset'])->middleware('throttle:6,1');
    Route::get('/invitations/accept', [InvitationController::class, 'show'])->middleware('throttle:20,1');
    Route::post('/invitations/accept', [InvitationController::class, 'accept'])->middleware('throttle:8,1');

    Route::get('/platform/overview', [PlatformAdminController::class, 'overview'])
        ->middleware(['auth', 'verified', 'platform_admin']);

    Route::middleware(['auth', 'verified', 'organization'])->group(function (): void {
        Route::get('/branches', [BranchController::class, 'index']);
        Route::post('/branches', [BranchController::class, 'store']);
        Route::post('/branches/{branch}/select', [BranchController::class, 'select']);
        Route::get('/dashboard', DashboardController::class)->middleware('branch');
        Route::middleware('branch')->prefix('academic-structure')->group(function (): void {
            Route::get('/', [AcademicStructureController::class, 'index']);
            Route::post('/years', [AcademicStructureController::class, 'storeYear']);
            Route::post('/levels', [AcademicStructureController::class, 'storeLevel']);
            Route::post('/subjects', [AcademicStructureController::class, 'storeSubject']);
        });
        Route::prefix('team')->group(function (): void {
            Route::get('/', [TeamController::class, 'index']);
            Route::post('/invitations', [TeamController::class, 'invite']);
            Route::delete('/invitations/{invitation}', [TeamController::class, 'revoke']);
            Route::patch('/members/{membership}', [TeamController::class, 'update']);
            Route::post('/roles', [TeamController::class, 'storeRole']);
        });
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
    });
});

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
Route::view('/reset-password/{token}', 'app')->name('password.reset');

Route::view('/{path?}', 'app')->where('path', '^(?!api|up).*$');
