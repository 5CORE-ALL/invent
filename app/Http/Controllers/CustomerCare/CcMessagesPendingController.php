<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Channels\AccountHealthMasterController;
use App\Http\Controllers\Controller;
use App\Models\AccountHealthMetricFieldDefinition;
use App\Models\CcMessagesPending;
use App\Models\ChannelMaster;
use App\Services\CustomerCare\MarketplacePendingMessagesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CcMessagesPendingController extends Controller
{
    public function __construct(
        protected MarketplacePendingMessagesService $pendingMessages
    ) {}

    public function index(): View
    {
        $this->pendingMessages->ensureSchema();

        return view('customer-care.messages-pending', [
            'channels' => $this->channelRows(),
            'pendingTotal' => CcMessagesPending::pendingTotal(),
        ]);
    }

    public function pull(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel_id' => 'required|integer|min:1',
        ]);

        $channel = ChannelMaster::query()->find((int) $validated['channel_id']);
        if ($channel === null) {
            return response()->json(['success' => false, 'message' => 'Channel not found.'], 422);
        }

        $row = $this->pendingMessages->fetchAndStore($channel);

        return response()->json([
            'success' => true,
            'row' => $row,
            'pending_total' => CcMessagesPending::pendingTotal(),
        ]);
    }

    public function saveCount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel_id' => 'required|integer|min:1',
            'pending_count' => 'required|integer|min:0|max:999999',
        ]);

        $channel = ChannelMaster::query()->find((int) $validated['channel_id']);
        if ($channel === null) {
            return response()->json(['success' => false, 'message' => 'Channel not found.'], 422);
        }

        $this->ensureTable();

        $user = Auth::user();
        $row = CcMessagesPending::query()->updateOrCreate(
            ['channel_id' => (int) $channel->id],
            [
                'pending_count' => (int) $validated['pending_count'],
                'updated_by_user_id' => $user?->id,
                'updated_by_name' => $user?->name,
            ]
        );

        return response()->json([
            'success' => true,
            'row' => [
                'channel_id' => (int) $row->channel_id,
                'pending_count' => (int) $row->pending_count,
            ],
        ]);
    }

    public function saveLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel_id' => 'required|integer|min:1',
            'value' => 'nullable|string|max:2048',
        ]);

        $channel = ChannelMaster::query()->find((int) $validated['channel_id']);
        if ($channel === null) {
            return response()->json(['success' => false, 'message' => 'Channel not found.'], 422);
        }

        $link = trim((string) ($validated['value'] ?? ''));
        if ($link !== '' && ! preg_match('#^(https?://|/|#)#i', $link)) {
            return response()->json([
                'success' => false,
                'message' => 'Enter a valid URL (https://… or a site path starting with /).',
            ], 422);
        }

        $this->ensureTable();

        $user = Auth::user();
        $row = CcMessagesPending::query()->updateOrCreate(
            ['channel_id' => (int) $channel->id],
            [
                'messages_link' => $link !== '' ? $link : null,
                'updated_by_user_id' => $user?->id,
                'updated_by_name' => $user?->name,
            ]
        );

        return response()->json([
            'success' => true,
            'channel_id' => (int) $row->channel_id,
            'value' => $row->messages_link,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function channelRows(): array
    {
        $hasLogo = Schema::hasColumn('channel_master', 'logo');
        $columns = ['id', 'channel'];
        if ($hasLogo) {
            $columns[] = 'logo';
        }

        $channels = ChannelMaster::query()
            ->where('status', 'Active')
            ->orderBy('channel')
            ->get($columns)
            ->filter(fn ($row) => ! empty($row->channel))
            ->values();

        $pendingMap = [];
        if (Schema::hasTable('cc_messages_pending') && $channels->isNotEmpty()) {
            $pendingMap = CcMessagesPending::query()
                ->whereIn('channel_id', $channels->pluck('id')->all())
                ->get()
                ->keyBy('channel_id');
        }

        $ahm = app(AccountHealthMasterController::class);
        $scopeToMLink = [];

        return $channels->map(function (ChannelMaster $c) use ($hasLogo, $pendingMap, $ahm, &$scopeToMLink) {
            $pending = $pendingMap[$c->id] ?? null;
            $storedLink = $pending ? trim((string) ($pending->messages_link ?? '')) : '';
            $scope = $ahm->definitionScopeForChannel($c);
            if (! array_key_exists($scope, $scopeToMLink)) {
                $scopeToMLink[$scope] = AccountHealthMetricFieldDefinition::query()
                    ->where('definition_scope', $scope)
                    ->whereNotNull('m_link')
                    ->where('m_link', '!=', '')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->value('m_link');
            }
            $mLink = trim((string) ($scopeToMLink[$scope] ?? ''));

            $status = $pending ? (string) ($pending->fetch_status ?? '') : '';
            $hasApi = $this->pendingMessages->driverFor($c) !== null;

            return [
                'id' => (int) $c->id,
                'channel' => (string) $c->channel,
                'logo' => $hasLogo ? ($c->logo ?? null) : null,
                'pending_count' => $pending && $status === MarketplacePendingMessagesService::STATUS_OK
                    ? (int) $pending->pending_count
                    : 0,
                'has_api' => $hasApi,
                'fetch_status' => $status !== '' ? $status : ($hasApi ? 'pending' : MarketplacePendingMessagesService::STATUS_UNSUPPORTED),
                'fetch_note' => $pending ? ($pending->fetch_note ?? null) : null,
                'last_fetched_at' => $pending && $pending->last_fetched_at
                    ? $pending->last_fetched_at->toIso8601String()
                    : null,
                'messages_link' => $storedLink !== '' ? $storedLink : ($mLink !== '' ? $mLink : null),
            ];
        })->values()->all();
    }

    protected function ensureTable(): void
    {
        $this->pendingMessages->ensureSchema();
    }
}
