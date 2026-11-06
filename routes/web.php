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
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentCardController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeachingGroupController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ParentAccessLinkController;
use App\Http\Controllers\ExaminationController;
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
    Route::post('/subscriptions/paymob/callback', [SubscriptionController::class, 'paymobCallback'])->middleware('throttle:30,1')->name('subscriptions.paymob-callback');
    Route::get('/parent/{token}', [ParentAccessLinkController::class, 'show'])->middleware('throttle:30,1');

    Route::get('/platform/overview', [PlatformAdminController::class, 'overview'])
        ->middleware(['auth', 'verified', 'platform_admin']);
    Route::middleware(['auth', 'verified', 'platform_admin'])->prefix('platform')->group(function (): void {
        Route::get('/plans', [PlatformAdminController::class, 'plans']);
        Route::patch('/plans/{plan}', [PlatformAdminController::class, 'updatePlan']);
        Route::get('/paymob-settings', [PlatformAdminController::class, 'paymobSettings']);
        Route::put('/paymob-settings', [PlatformAdminController::class, 'updatePaymobSettings']);
    });

    Route::middleware(['auth', 'verified', 'organization'])->group(function (): void {
        Route::get('/subscriptions', [SubscriptionController::class, 'status']);
        Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout'])->middleware('throttle:5,1');
        Route::middleware('branch')->group(function (): void {
            Route::post('/parent-links', [ParentAccessLinkController::class, 'store']);
            Route::delete('/parent-links/{linkId}', [ParentAccessLinkController::class, 'revoke']);
        });
        Route::middleware('branch')->prefix('attendance')->group(function (): void {
            Route::get('/sessions', [AttendanceController::class, 'sessions']);
            Route::post('/sessions', [AttendanceController::class, 'store']);
            Route::post('/sessions/{sessionId}/scan', [AttendanceController::class, 'scan']);
            Route::get('/sessions/{sessionId}/report', [AttendanceController::class, 'report']);
            Route::get('/sessions/{sessionId}/corrections', [AttendanceController::class, 'corrections']);
            Route::put('/sessions/{sessionId}/records', [AttendanceController::class, 'bulkMark']);
            Route::post('/sessions/{sessionId}/close', [AttendanceController::class, 'close']);
        });
        Route::middleware('branch')->prefix('examinations')->group(function (): void {
            Route::get('/', [ExaminationController::class, 'index']);
            Route::post('/', [ExaminationController::class, 'store']);
        });
        Route::get('/branches', [BranchController::class, 'index']);
        Route::post('/branches', [BranchController::class, 'store']);
        Route::post('/branches/{branch}/select', [BranchController::class, 'select']);
        Route::get('/dashboard', DashboardController::class)->middleware('branch');
        Route::middleware('branch')->prefix('students')->group(function (): void {
            Route::post('/cards/issue', [StudentCardController::class, 'issue']);
            Route::post('/cards/revoke', [StudentCardController::class, 'revoke']);
            Route::get('/import-template', [StudentImportController::class, 'template']);
            Route::post('/imports', [StudentImportController::class, 'store']);
            Route::get('/imports/{publicId}', [StudentImportController::class, 'show']);
            Route::post('/imports/{publicId}/commit', [StudentImportController::class, 'commit']);
            Route::get('/imports/{publicId}/errors', [StudentImportController::class, 'errors']);
            Route::get('/', [StudentController::class, 'index']);
            Route::post('/', [StudentController::class, 'store']);
            Route::post('/bulk-archive', [StudentController::class, 'bulkArchive']);
            Route::patch('/{publicId}', [StudentController::class, 'update']);
            Route::post('/{publicId}/archive', [StudentController::class, 'archive']);
        });
        Route::middleware('branch')->prefix('teaching-groups')->group(function (): void {
            Route::get('/', [TeachingGroupController::class, 'index']);
            Route::post('/', [TeachingGroupController::class, 'store']);
            Route::get('/schedules', [TeachingGroupController::class, 'schedules']);
            Route::post('/schedules', [TeachingGroupController::class, 'storeSchedule']);
        });
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
