<?php

namespace App\Http\Controllers;

use App\Models\ChatAudit;
use App\Models\ChatHealthEvent;
use App\Models\ChatMessage;
use App\Models\ChatPresence;
use App\Support\ChatPresence as Presence;
use App\Support\ChatWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ChatHealthController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        abort_unless($user && ChatWorkspace::canManageChannels($user), 403);

        return view('chat.health', $this->stats());
    }

    public function data(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user && ChatWorkspace::canManageChannels($user), 403);

        return response()->json($this->stats());
    }

    public function retry(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user && ChatWorkspace::canManageChannels($user), 403);

        $action = $request->input('action');
        $error = null;
        if ($action === 'retry_failed_jobs' && Schema::hasTable('failed_jobs')) {
            try {
                Artisan::call('queue:retry', ['id' => ['all']]);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        if ($request->wantsJson()) {
            if ($error) {
                return response()->json(['message' => $error], 422);
            }

            return response()->json(['ok' => true, 'stats' => $this->stats()]);
        }

        return redirect()
            ->route('chat.health')
            ->with($error ? 'error' : 'status', $error ?: 'Retry requested.');
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $driver = (string) config('broadcasting.default');
        $realtime = in_array($driver, ['reverb', 'pusher', 'ably'], true) ? 'configured' : 'polling';
        $active = 0;
        if (Schema::hasTable('chat_presences')) {
            $active = ChatPresence::query()
                ->where('last_seen_at', '>=', now()->subSeconds(Presence::ONLINE_SECONDS))
                ->count();
        }

        $today = ChatMessage::query()->whereDate('created_at', now()->toDateString())->count();
        $failedMessages = Schema::hasTable('chat_health_events')
            ? ChatHealthEvent::query()->where('type', 'failed_delivery')->where('created_at', '>=', now()->subDay())->count()
            : 0;
        $reconnects = Schema::hasTable('chat_health_events')
            ? ChatHealthEvent::query()->where('type', 'reconnect')->where('created_at', '>=', now()->subDay())->count()
            : 0;
        $pendingJobs = Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0;
        $failedJobs = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;
        $failedNotifications = Schema::hasTable('failed_jobs')
            ? (int) DB::table('failed_jobs')->where('payload', 'like', '%Notification%')->count()
            : 0;

        $recentErrors = Schema::hasTable('chat_health_events')
            ? ChatHealthEvent::query()->latest('id')->limit(25)->get()
            : collect();
        $recentAudits = Schema::hasTable('chat_audits')
            ? ChatAudit::query()->latest('id')->limit(25)->get()
            : collect();

        return [
            'driver' => $driver,
            'realtime' => $realtime,
            'active_connections' => $active,
            'messages_today' => $today,
            'failed_messages' => $failedMessages,
            'failed_notifications' => $failedNotifications,
            'pending_jobs' => $pendingJobs,
            'queue_failures' => $failedJobs,
            'reconnects_today' => $reconnects,
            'recent_errors' => $recentErrors,
            'recent_audits' => $recentAudits,
        ];
    }
}
