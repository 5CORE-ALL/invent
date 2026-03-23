<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use App\Models\ShippingFollowup;
use App\Models\ShippingFollowupArchive;
use App\Models\ShippingPlatform;
use App\Models\ShippingReportIssue;
use App\Models\ShippingReportLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShippingController extends Controller
{
    public const SLOT_AM_930 = 'am_930_est';

    public const SLOT_PM_330 = 'pm_330_est';

    public function index()
    {
        $this->ensurePlatforms();

        return view('customer-care.shipping', [
            'timeSlots' => [
                self::SLOT_AM_930 => '9:30 AM EST',
                self::SLOT_PM_330 => '3:30 PM EST',
            ],
        ]);
    }

    public function overview(Request $request)
    {
        $this->ensurePlatforms();

        $days = min(30, max(7, (int) $request->query('days', 14)));
        $start = Carbon::now('America/New_York')->subDays($days)->toDateString();

        $lines = ShippingReportLine::query()
            ->whereDate('report_date', '>=', $start)
            ->get(['is_cleared', 'report_date', 'time_slot', 'shipping_platform_id']);

        $total = $lines->count();
        $cleared = $lines->where('is_cleared', true)->count();
        $pct = $total > 0 ? round(100 * $cleared / $total, 1) : null;

        $byDay = $lines->groupBy(fn ($r) => $r->report_date->format('Y-m-d'))
            ->map(function ($group) {
                $t = $group->count();

                return [
                    'total' => $t,
                    'cleared' => $group->where('is_cleared', true)->count(),
                    'pct' => $t > 0 ? round(100 * $group->where('is_cleared', true)->count() / $t, 1) : 0,
                ];
            })->sortKeys();

        $platforms = ShippingPlatform::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $indexSync = $platforms->map(function ($p) {
            $last = ShippingReportLine::query()
                ->where('shipping_platform_id', $p->id)
                ->max('updated_at');

            return [
                'id' => $p->id,
                'name' => $p->name,
                'last_report_activity_at' => $last,
            ];
        });

        return response()->json([
            'performance' => [
                'window_days' => $days,
                'report_lines_total' => $total,
                'cleared_total' => $cleared,
                'cleared_pct' => $pct,
                'by_day' => $byDay,
                'note' => 'Performance is derived from daily report checklists (platforms marked cleared). Order ship-time automation can be wired later.',
            ],
            'shipping_index' => $indexSync,
            'open_followups' => ShippingFollowup::query()->where('status', ShippingFollowup::STATUS_OPEN)->count(),
        ]);
    }

    public function reportState(Request $request)
    {
        $this->ensurePlatforms();

        $request->validate([
            'report_date' => ['required', 'date'],
            'time_slot' => ['required', 'in:' . self::SLOT_AM_930 . ',' . self::SLOT_PM_330],
        ]);

        $platforms = ShippingPlatform::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $lines = ShippingReportLine::query()
            ->whereDate('report_date', $request->report_date)
            ->where('time_slot', $request->time_slot)
            ->with('issues')
            ->get()
            ->keyBy('shipping_platform_id');

        $payload = $platforms->map(function ($p) use ($lines) {
            $line = $lines->get($p->id);
            $issues = $line?->issues ?? collect();
            $visibleIssues = $issues->filter(fn (ShippingReportIssue $i) => !$i->hidden_from_report);
            // Checkbox = report checkpoint; hidden issues stay in DB + open follow-ups until Resolved.
            $isCleared = $line ? (bool) $line->is_cleared : false;

            return [
                'platform_id' => $p->id,
                'platform_name' => $p->name,
                'line_id' => $line?->id,
                'is_cleared' => $isCleared,
                'issues' => $visibleIssues->map(fn (ShippingReportIssue $i) => [
                    'id' => $i->id,
                    'order_number' => $i->order_number,
                    'sku' => $i->sku,
                    'reason' => $i->reason,
                ])->values(),
                'updated_at' => $line?->updated_at,
            ];
        });

        return response()->json(['platforms' => $payload]);
    }

    public function reportSave(Request $request)
    {
        $this->ensurePlatforms();

        $request->validate([
            'report_date' => ['required', 'date'],
            'time_slot' => ['required', 'in:' . self::SLOT_AM_930 . ',' . self::SLOT_PM_330],
            'shipping_platform_id' => ['required', 'exists:shipping_platforms,id'],
            'is_cleared' => ['required', 'boolean'],
            'order_number' => ['nullable', 'string', 'max:191'],
            'sku' => ['nullable', 'string', 'max:191'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ]);

        if (!$request->boolean('is_cleared')) {
            $request->validate([
                'order_number' => ['required', 'string', 'max:191'],
                'sku' => ['required', 'string', 'max:191'],
                'reason' => ['required', 'string', 'max:5000'],
            ]);
        }

        $userId = $request->user()?->id;

        return DB::transaction(function () use ($request, $userId) {
            $line = ShippingReportLine::query()->updateOrCreate(
                [
                    'report_date' => $request->report_date,
                    'time_slot' => $request->time_slot,
                    'shipping_platform_id' => $request->shipping_platform_id,
                ],
                [
                    'is_cleared' => $request->boolean('is_cleared'),
                    'order_number' => null,
                    'sku' => null,
                    'reason' => null,
                    'user_id' => $userId,
                ]
            );

            if ($line->is_cleared) {
                // Hide all issues from the Report UI; follow-ups stay open until Resolved on Follow-up tab.
                $line->issues()->update(['hidden_from_report' => true]);
            }

            if (!$line->is_cleared) {
                $issue = $line->issues()->create([
                    'order_number' => $request->order_number,
                    'sku' => $request->sku,
                    'reason' => $request->reason,
                    'hidden_from_report' => false,
                ]);

                ShippingFollowup::query()->create([
                    'shipping_report_line_id' => $line->id,
                    'shipping_report_issue_id' => $issue->id,
                    'shipping_platform_id' => $line->shipping_platform_id,
                    'report_date' => $line->report_date,
                    'time_slot' => $line->time_slot,
                    'order_number' => $issue->order_number,
                    'sku' => $issue->sku,
                    'reason' => $issue->reason,
                    'status' => ShippingFollowup::STATUS_OPEN,
                    'created_by' => $userId,
                ]);
            }

            $line->refresh();
            $line->load('issues');

            return response()->json([
                'ok' => true,
                'line' => [
                    'id' => $line->id,
                    'is_cleared' => (bool) $line->is_cleared,
                    'issues_count' => $line->issues->count(),
                    'updated_at' => $line->updated_at,
                ],
            ]);
        });
    }

    public function reportIssueDestroy(ShippingReportIssue $shippingReportIssue)
    {
        return DB::transaction(function () use ($shippingReportIssue) {
            $line = $shippingReportIssue->reportLine;
            if (!$line) {
                return response()->json(['message' => 'Invalid issue.'], 422);
            }

            ShippingFollowup::query()
                ->where('shipping_report_issue_id', $shippingReportIssue->id)
                ->where('status', ShippingFollowup::STATUS_OPEN)
                ->delete();

            $shippingReportIssue->delete();

            if ($line->issues()->count() === 0) {
                $line->update([
                    'is_cleared' => true,
                    'order_number' => null,
                    'sku' => null,
                    'reason' => null,
                ]);
            }

            return response()->json(['ok' => true]);
        });
    }

    public function platformsList()
    {
        $this->ensurePlatforms();

        $rows = ShippingPlatform::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['platforms' => $rows]);
    }

    public function followupsList(Request $request)
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'shipping_platform_id' => ['nullable', 'exists:shipping_platforms,id'],
            'time_slot' => ['nullable', 'in:' . self::SLOT_AM_930 . ',' . self::SLOT_PM_330],
            'search' => ['nullable', 'string', 'max:191'],
        ]);

        $q = ShippingFollowup::query()
            ->with(['platform:id,name'])
            ->where('status', ShippingFollowup::STATUS_OPEN);

        if ($request->filled('shipping_platform_id')) {
            $q->where('shipping_platform_id', $request->shipping_platform_id);
        }

        if ($request->filled('time_slot')) {
            $q->where('time_slot', $request->time_slot);
        }

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $from = $request->input('date_from') ?: '1970-01-01';
            $to = $request->input('date_to') ?: '2099-12-31';
            $q->where(function ($qq) use ($from, $to) {
                $qq->where(function ($q1) use ($from, $to) {
                    $q1->whereNotNull('report_date')
                        ->whereDate('report_date', '>=', $from)
                        ->whereDate('report_date', '<=', $to);
                })->orWhere(function ($q2) use ($from, $to) {
                    $q2->whereNull('report_date')
                        ->whereDate('created_at', '>=', $from)
                        ->whereDate('created_at', '<=', $to);
                });
            });
        }

        if ($request->filled('search')) {
            $s = '%' . addcslashes(trim($request->search), '%_\\') . '%';
            $q->where(function ($qq) use ($s) {
                $qq->where('order_number', 'like', $s)
                    ->orWhere('sku', 'like', $s)
                    ->orWhere('reason', 'like', $s);
            });
        }

        $rows = $q->orderByDesc('updated_at')
            ->limit(500)
            ->get()
            ->map(function (ShippingFollowup $f) {
                return [
                    'id' => $f->id,
                    'platform' => $f->platform?->name,
                    'report_date' => $f->report_date?->format('Y-m-d'),
                    'time_slot' => $f->time_slot,
                    'time_slot_label' => $this->slotLabel($f->time_slot),
                    'order_number' => $f->order_number,
                    'sku' => $f->sku,
                    'reason' => $f->reason,
                    'updated_at' => $f->updated_at,
                ];
            });

        return response()->json(['followups' => $rows]);
    }

    public function followupsStore(Request $request)
    {
        $request->validate([
            'order_number' => ['required', 'string', 'max:191'],
            'sku' => ['required', 'string', 'max:191'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'shipping_platform_id' => ['nullable', 'exists:shipping_platforms,id'],
        ]);

        $userId = $request->user()?->id;

        $f = ShippingFollowup::query()->create([
            'shipping_report_line_id' => null,
            'shipping_platform_id' => $request->shipping_platform_id,
            'report_date' => null,
            'time_slot' => null,
            'order_number' => $request->order_number,
            'sku' => $request->sku,
            'reason' => $request->reason,
            'status' => ShippingFollowup::STATUS_OPEN,
            'created_by' => $userId,
        ]);

        return response()->json([
            'ok' => true,
            'followup' => ['id' => $f->id],
        ], 201);
    }

    public function clearedList(Request $request)
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'shipping_platform_id' => ['nullable', 'exists:shipping_platforms,id'],
            'time_slot' => ['nullable', 'in:' . self::SLOT_AM_930 . ',' . self::SLOT_PM_330],
            'search' => ['nullable', 'string', 'max:191'],
        ]);

        $q = ShippingFollowupArchive::query()
            ->with(['resolver:id,name', 'platform:id,name']);

        if ($request->filled('shipping_platform_id')) {
            $q->where('shipping_platform_id', $request->shipping_platform_id);
        }

        if ($request->filled('time_slot')) {
            $q->where('time_slot', $request->time_slot);
        }

        if ($request->filled('date_from')) {
            $q->whereDate('archived_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->whereDate('archived_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $s = '%' . addcslashes(trim($request->search), '%_\\') . '%';
            $q->where(function ($qq) use ($s) {
                $qq->where('order_number', 'like', $s)
                    ->orWhere('sku', 'like', $s)
                    ->orWhere('reason', 'like', $s);
            });
        }

        $rows = $q->orderByDesc('archived_at')
            ->limit(500)
            ->get()
            ->map(function (ShippingFollowupArchive $a) {
                return [
                    'id' => $a->id,
                    'platform' => $a->platform?->name,
                    'report_date' => $a->report_date?->format('Y-m-d'),
                    'time_slot' => $a->time_slot,
                    'time_slot_label' => $this->slotLabel($a->time_slot),
                    'order_number' => $a->order_number,
                    'sku' => $a->sku,
                    'reason' => $a->reason,
                    'cleared_by' => $a->resolver?->name ?? '—',
                    'cleared_at' => $a->resolved_at?->toIso8601String(),
                    'archived_at' => $a->archived_at?->toIso8601String(),
                ];
            });

        return response()->json(['items' => $rows]);
    }

    public function clearedRestore(Request $request, ShippingFollowupArchive $shippingFollowupArchive)
    {
        $userId = $request->user()?->id;

        return DB::transaction(function () use ($shippingFollowupArchive, $userId) {
            $archive = $shippingFollowupArchive;

            $line = $archive->shipping_report_line_id
                ? ShippingReportLine::query()->find($archive->shipping_report_line_id)
                : null;

            $lineId = null;
            $issueId = null;

            if ($line) {
                $line->update([
                    'is_cleared' => false,
                    'order_number' => null,
                    'sku' => null,
                    'reason' => null,
                    'user_id' => $userId,
                ]);

                $issue = $line->issues()->create([
                    'order_number' => $archive->order_number,
                    'sku' => $archive->sku,
                    'reason' => $archive->reason,
                ]);
                $lineId = $line->id;
                $issueId = $issue->id;
            }

            $followup = ShippingFollowup::query()->create([
                'shipping_report_line_id' => $lineId,
                'shipping_report_issue_id' => $issueId,
                'shipping_platform_id' => $archive->shipping_platform_id ?? $line?->shipping_platform_id,
                'report_date' => $archive->report_date ?? $line?->report_date,
                'time_slot' => $archive->time_slot ?? $line?->time_slot,
                'order_number' => $archive->order_number,
                'sku' => $archive->sku,
                'reason' => $archive->reason,
                'status' => ShippingFollowup::STATUS_OPEN,
                'created_by' => $userId,
            ]);

            $archive->delete();

            return response()->json([
                'ok' => true,
                'followup_id' => $followup->id,
            ]);
        });
    }

    public function followupsResolve(Request $request, ShippingFollowup $shippingFollowup)
    {
        if ($shippingFollowup->status !== ShippingFollowup::STATUS_OPEN) {
            return response()->json(['message' => 'Already resolved or invalid.'], 422);
        }

        $userId = $request->user()?->id;

        DB::transaction(function () use ($shippingFollowup, $userId) {
            $resolvedAt = now();

            $lineId = $shippingFollowup->shipping_report_line_id;
            $issueId = $shippingFollowup->shipping_report_issue_id;

            ShippingFollowupArchive::query()->create([
                'original_followup_id' => $shippingFollowup->id,
                'shipping_report_line_id' => $shippingFollowup->shipping_report_line_id,
                'shipping_report_issue_id' => $shippingFollowup->shipping_report_issue_id,
                'shipping_platform_id' => $shippingFollowup->shipping_platform_id,
                'report_date' => $shippingFollowup->report_date,
                'time_slot' => $shippingFollowup->time_slot,
                'order_number' => $shippingFollowup->order_number,
                'sku' => $shippingFollowup->sku,
                'reason' => $shippingFollowup->reason,
                'resolved_at' => $resolvedAt,
                'resolved_by' => $userId,
                'created_by' => $shippingFollowup->created_by,
                'created_at_followup' => $shippingFollowup->created_at,
                'archived_at' => now(),
            ]);

            $shippingFollowup->delete();

            if ($issueId) {
                $issue = ShippingReportIssue::query()->find($issueId);
                if ($issue) {
                    $line = $issue->reportLine;
                    $issue->delete();
                    if ($line && $line->issues()->count() === 0) {
                        $line->update([
                            'is_cleared' => true,
                            'order_number' => null,
                            'sku' => null,
                            'reason' => null,
                            'user_id' => $userId,
                        ]);
                    }
                }
            } elseif ($lineId) {
                $line = ShippingReportLine::query()->find($lineId);
                if ($line) {
                    ShippingFollowup::query()
                        ->where('shipping_report_line_id', $line->id)
                        ->where('status', ShippingFollowup::STATUS_OPEN)
                        ->delete();
                    $line->issues()->delete();
                    $line->update([
                        'is_cleared' => true,
                        'order_number' => null,
                        'sku' => null,
                        'reason' => null,
                        'user_id' => $userId,
                    ]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    private function slotLabel(?string $slot): ?string
    {
        return match ($slot) {
            self::SLOT_AM_930 => '9:30 AM EST',
            self::SLOT_PM_330 => '3:30 PM EST',
            default => $slot,
        };
    }

    private function ensurePlatforms(): void
    {
        if (ShippingPlatform::query()->exists()) {
            return;
        }

        $names = config('shipping.default_platforms', []);
        $order = 0;
        foreach ($names as $name) {
            ShippingPlatform::query()->create([
                'name' => $name,
                'sort_order' => $order++,
                'is_active' => true,
            ]);
        }
    }
}
