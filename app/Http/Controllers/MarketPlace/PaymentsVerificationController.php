<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Channels\ChannelMasterController;
use App\Http\Controllers\Controller;
use App\Models\PaymentVerification;
use App\Models\PaymentVerificationHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Payments Verification — one row per active channel.
 *
 * The channel columns (Img, MP, Type, Sales) come from the same data as the
 * Active Channel Master page, so the numbers always match it. The payment
 * columns (PMT RECD, Bank Account, Link) are stored here.
 */
class PaymentsVerificationController extends Controller
{
    public function index()
    {
        return view('market-places.payments_verification');
    }

    public function data(): JsonResponse
    {
        if (! Schema::hasTable('payment_verifications')) {
            return response()->json([
                'success' => false,
                'message' => 'Run php artisan migrate (payment_verifications table is missing).',
                'data' => [],
            ], 500);
        }

        try {
            $channelRows = $this->activeChannelRows();

            $saved = PaymentVerification::query()->get()->keyBy('channel_key');
            $historyCounts = PaymentVerificationHistory::query()
                ->select('channel_key', DB::raw('COUNT(*) as c'))
                ->groupBy('channel_key')
                ->pluck('c', 'channel_key');

            $data = [];
            foreach ($channelRows as $row) {
                $name = trim((string) ($row['Channel '] ?? $row['Channel'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $key = PaymentVerification::keyFor($name);
                $mine = $saved->get($key);

                $data[] = [
                    'channel_key' => $key,
                    'channel' => $name,
                    'logo' => $row['logo'] ?? null,
                    'seller_link' => $row['seller_link'] ?? null,
                    'type' => $row['type'] ?? null,
                    'sales' => (float) preg_replace('/[^0-9.\-]/', '', (string) ($row['L30 Sales'] ?? 0)),
                    'pmt_recd' => $mine?->pmt_recd,
                    'bank_account' => $mine?->bank_account,
                    'link' => $mine?->link,
                    'history_count' => (int) ($historyCounts[$key] ?? 0),
                ];
            }

            return response()->json(['success' => true, 'data' => $data, 'count' => count($data)]);
        } catch (\Throwable $e) {
            Log::error('Payments Verification data failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    /** Save the PMT RECD amount (up to six characters) and keep a history entry. */
    public function saveAmount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'required|string|max:191',
            'amount' => ['required', 'string', 'max:6', 'regex:/^\d+(\.\d+)?$/'],
        ], [
            'amount.max' => 'Amount can be at most 6 characters.',
            'amount.regex' => 'Enter a number, for example 1250 or 99.5.',
        ]);

        $channel = trim($data['channel']);
        $key = PaymentVerification::keyFor($channel);
        if ($key === '') {
            return response()->json(['success' => false, 'message' => 'Channel is required.'], 422);
        }
        $by = (string) ($request->user()->name ?? 'unknown');

        DB::transaction(function () use ($key, $channel, $data, $by) {
            $row = PaymentVerification::firstOrNew(['channel_key' => $key]);
            $row->channel = $channel;
            $row->pmt_recd = $data['amount'];
            $row->updated_by = $by;
            $row->save();

            PaymentVerificationHistory::create([
                'channel_key' => $key,
                'channel' => $channel,
                'amount' => $data['amount'],
                'recorded_by' => $by,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'PMT RECD saved.',
            'data' => [
                'channel_key' => $key,
                'pmt_recd' => $data['amount'],
                'history_count' => PaymentVerificationHistory::where('channel_key', $key)->count(),
            ],
        ]);
    }

    /** Save Bank Account and Link. */
    public function saveDetails(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'required|string|max:191',
            'bank_account' => 'nullable|string|max:255',
            'link' => 'nullable|url|max:1000',
        ]);

        $channel = trim($data['channel']);
        $key = PaymentVerification::keyFor($channel);
        if ($key === '') {
            return response()->json(['success' => false, 'message' => 'Channel is required.'], 422);
        }

        $row = PaymentVerification::firstOrNew(['channel_key' => $key]);
        $row->channel = $channel;
        $row->bank_account = isset($data['bank_account']) && trim($data['bank_account']) !== '' ? trim($data['bank_account']) : null;
        $row->link = isset($data['link']) && trim($data['link']) !== '' ? trim($data['link']) : null;
        $row->updated_by = (string) ($request->user()->name ?? 'unknown');
        $row->save();

        return response()->json([
            'success' => true,
            'message' => 'Saved.',
            'data' => [
                'channel_key' => $key,
                'bank_account' => $row->bank_account,
                'link' => $row->link,
            ],
        ]);
    }

    /** PMT RECD history for one channel (oldest first, ready for a chart). */
    public function history(Request $request): JsonResponse
    {
        $channel = trim((string) $request->query('channel', ''));
        $key = PaymentVerification::keyFor($channel);
        if ($key === '') {
            return response()->json(['success' => false, 'message' => 'Channel is required.'], 422);
        }

        $rows = PaymentVerificationHistory::query()
            ->where('channel_key', $key)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['amount', 'recorded_by', 'created_at']);

        return response()->json([
            'success' => true,
            'channel' => $channel,
            'history' => $rows->map(fn ($r) => [
                'amount' => (string) $r->amount,
                'value' => (float) $r->amount,
                'by' => $r->recorded_by,
                'at' => $r->created_at?->format('Y-m-d H:i'),
            ])->values(),
        ]);
    }

    /**
     * Rows of the Active Channel Master table (same source, same Sales figure).
     *
     * @return array<int, array<string, mixed>>
     */
    private function activeChannelRows(): array
    {
        $response = app(ChannelMasterController::class)->getViewChannelDataFast(
            Request::create('/channels-master-data', 'GET', ['size' => 10000, 'page' => 1])
        );

        $payload = $response instanceof JsonResponse ? $response->getData(true) : (is_array($response) ? $response : []);
        $rows = $payload['data'] ?? [];

        return is_array($rows) ? $rows : [];
    }
}
