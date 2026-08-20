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
    Route::get('/ping', function () {
        $base = rtrim((string) config('app.url'), '/');

        return response()->json([
            'ok' => true,
            'service' => '5core-attendance-agent',
            'version' => config('attendance.agent_version', '1.0.0'),
            'download_page_url' => $base.'/attendance/agent',
            'download_url' => $base.'/attendance/agent/download',
            'update_message' => 'A new version of 5Core Attendance is available. Run the installer to update — no uninstall needed.',
            // Portal web Google OAuth is preferred; desktop client id is optional/legacy.
            'google_sign_in' => (bool) config('services.google.client_id'),
            'google_client_id' => config('services.google_desktop.client_id'),
        ]);
    })->name('ping');

    Route::post('/login', [AttendanceAgentController::class, 'login'])->name('login');
    Route::post('/google-login', [AttendanceAgentController::class, 'googleLogin'])->name('google-login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/config', [AttendanceAgentController::class, 'config'])->name('config');
        Route::get('/status', [AttendanceAgentController::class, 'status'])->name('status');
        Route::post('/clock-in', [AttendanceAgentController::class, 'clockIn'])->name('clock-in');
        Route::post('/clock-out', [AttendanceAgentController::class, 'clockOut'])->name('clock-out');
        Route::post('/pause', [AttendanceAgentController::class, 'pause'])->name('pause');
        Route::post('/resume', [AttendanceAgentController::class, 'resume'])->name('resume');
        Route::post('/heartbeat', [AttendanceAgentController::class, 'heartbeat'])->name('heartbeat');
        Route::post('/screenshot', [AttendanceAgentController::class, 'screenshot'])->name('screenshot');
        Route::get('/live-command', [AttendanceAgentController::class, 'liveCommand'])->name('live-command');
        Route::post('/live-frame', [AttendanceAgentController::class, 'liveFrame'])->name('live-frame');
    });
});
