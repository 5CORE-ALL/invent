<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\CustomerCare\Concerns\HasOptionalOrderNumberField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CarrierIssueController extends IssueBoardControllerBase
{
    use HasOptionalOrderNumberField;

    protected function extraRowFields(object $row): array
    {
        return [
            'claim_filed' => (bool) ($row->claim_filed ?? false),
            'amp_usd' => $row->amp_usd !== null && $row->amp_usd !== '' ? (string) $row->amp_usd : '',
            'claim_received' => (bool) ($row->claim_received ?? false),
            'issue_carrier' => $row->issue_carrier !== null && $row->issue_carrier !== '' ? (string) $row->issue_carrier : '',
        ];
    }

    public function updateIssueCarrier(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'issue_carrier' => 'nullable|string|in:USPS,UPS,FEDEX,GOFO',
        ]);

        $existing = DB::table($this->issuesTable())->where('id', $id)->first();
        if (! $existing) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $value = isset($validated['issue_carrier']) ? trim((string) $validated['issue_carrier']) : '';
        $value = $value === '' ? null : $value;

        DB::table($this->issuesTable())->where('id', $id)->update([
            'issue_carrier' => $value,
            'updated_at' => now(),
        ]);

        return response()->json([
            'issue_carrier' => $value ?? '',
        ]);
    }

    public function updateAmpUsd(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'amp_usd' => 'nullable|string|max:6',
        ]);

        $existing = DB::table($this->issuesTable())->where('id', $id)->first();
        if (! $existing) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $value = isset($validated['amp_usd']) ? trim((string) $validated['amp_usd']) : '';
        if (strlen($value) > 6) {
            $value = substr($value, 0, 6);
        }

        DB::table($this->issuesTable())->where('id', $id)->update([
            'amp_usd' => $value === '' ? null : $value,
            'updated_at' => now(),
        ]);

        return response()->json([
            'amp_usd' => $value,
        ]);
    }

    public function toggleClaimFiled(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'claim_filed' => 'required|boolean',
        ]);

        $existing = DB::table($this->issuesTable())->where('id', $id)->first();
        if (! $existing) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        DB::table($this->issuesTable())->where('id', $id)->update([
            'claim_filed' => $validated['claim_filed'],
            'updated_at' => now(),
        ]);

        return response()->json([
            'claim_filed' => (bool) $validated['claim_filed'],
        ]);
    }

    public function toggleClaimReceived(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'claim_received' => 'required|boolean',
        ]);

        $existing = DB::table($this->issuesTable())->where('id', $id)->first();
        if (! $existing) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        DB::table($this->issuesTable())->where('id', $id)->update([
            'claim_received' => $validated['claim_received'],
            'updated_at' => now(),
        ]);

        return response()->json([
            'claim_received' => (bool) $validated['claim_received'],
        ]);
    }

    public function claimsStats(): JsonResponse
    {
        $rows = DB::table($this->issuesTable())
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->get(['claim_filed', 'claim_received', 'amp_usd']);

        $filedCount = 0;
        $filedAmount = 0.0;
        $receivedCount = 0;
        $receivedAmount = 0.0;
        $pendingCount = 0;
        $pendingAmount = 0.0;

        foreach ($rows as $row) {
            $amt = self::parseAmpUsdAmount($row->amp_usd ?? null);
            $cf = (bool) ($row->claim_filed ?? false);
            $cr = (bool) ($row->claim_received ?? false);

            if ($cf) {
                $filedCount++;
                $filedAmount += $amt;
            }
            if ($cr) {
                $receivedCount++;
                $receivedAmount += $amt;
            }
            if ($cf && ! $cr) {
                $pendingCount++;
                $pendingAmount += $amt;
            }
        }

        return response()->json([
            'filed' => [
                'count' => $filedCount,
                'amount' => round($filedAmount, 2),
            ],
            'pending' => [
                'count' => $pendingCount,
                'amount' => round($pendingAmount, 2),
            ],
            'received' => [
                'count' => $receivedCount,
                'amount' => round($receivedAmount, 2),
            ],
        ]);
    }

    private static function parseAmpUsdAmount(mixed $raw): float
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') {
            return 0.0;
        }
        $s = preg_replace('/[^0-9.\-]/', '', $s) ?? '';
        if ($s === '' || $s === '-' || $s === '.') {
            return 0.0;
        }

        return round((float) $s, 2);
    }

    protected function viewName(): string
    {
        return 'customer-care.carrier_issue';
    }

    protected function issuesTable(): string
    {
        return 'carrier_issue_issues';
    }

    protected function historyTable(): string
    {
        return 'carrier_issue_issue_histories';
    }

    protected function moduleKey(): string
    {
        return 'carrier_issue';
    }
}
