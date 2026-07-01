<?php

use App\Http\Controllers\Attendance\AttendanceAgentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Attendance Desktop Agent API (Electron app — Sanctum bearer tokens)
|--------------------------------------------------------------------------
| Registered before web.php catch-all routes so /attendance/desktop-api/*
| is not swallowed by {first}/{second}/{third} auth routes.
*/

Route::prefix('attendance/desktop-api')->name('attendance.desktop-api.')->group(function () {
    Route::get('/ping', fn () => response()->json([
        'ok' => true,
        'service' => '5core-attendance-agent',
        'version' => config('attendance.agent_version', '1.0.0'),
    ]))->name('ping');

    Route::post('/login', [AttendanceAgentController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/config', [AttendanceAgentController::class, 'config'])->name('config');
        Route::get('/status', [AttendanceAgentController::class, 'status'])->name('status');
        Route::post('/clock-in', [AttendanceAgentController::class, 'clockIn'])->name('clock-in');
        Route::post('/clock-out', [AttendanceAgentController::class, 'clockOut'])->name('clock-out');
        Route::post('/pause', [AttendanceAgentController::class, 'pause'])->name('pause');
        Route::post('/resume', [AttendanceAgentController::class, 'resume'])->name('resume');
        Route::post('/heartbeat', [AttendanceAgentController::class, 'heartbeat'])->name('heartbeat');
        Route::post('/screenshot', [AttendanceAgentController::class, 'screenshot'])->name('screenshot');
    });
});
