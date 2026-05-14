<?php

namespace App\Http\Controllers;

use App\Models\AuditAttachment;
use App\Models\AuditGrade;
use App\Models\AuditParameter;
use App\Models\AuditResult;
use App\Models\AuditResultItem;
use App\Models\ChannelMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AuditMasterController extends Controller
{
    /**
     * Emails allowed to add / edit / archive audit parameters from the
     * inline admin controls inside the audit modal.
     * Comparison is case-insensitive.
     */
    private const ADMIN_EMAILS = [
        'president@5core.com',
        'sr.manager@5core.com',
    ];

    /**
     * True if the currently authenticated user is allowed to manage
     * (add / edit / archive) audit parameters.
     */
    public function isAuditAdmin(): bool
    {
        $user = Auth::user();
        if (! $user) return false;
        $email = strtolower(trim((string) ($user->email ?? '')));
        return $email !== '' && in_array($email, self::ADMIN_EMAILS, true);
    }

    /**
     * Fetch the active channels list from the channel_master table.
     * Used to power the "Channels" column / filter on every audit page.
     */
    private function getActiveChannels(): array
    {
        return ChannelMaster::where('status', 'Active')
            ->orderBy('channel')
            ->pluck('channel')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Fetch active channels with their logos from channel_master.
     * Each entry: ['channel' => string, 'logo' => string|null].
     */
    private function getActiveChannelsWithLogo(): array
    {
        $hasLogo = Schema::hasColumn('channel_master', 'logo');

        $columns = ['channel'];
        if ($hasLogo) {
            $columns[] = 'logo';
        }

        return ChannelMaster::where('status', 'Active')
            ->orderBy('channel')
            ->get($columns)
            ->filter(fn ($row) => !empty($row->channel))
            ->map(fn ($row) => [
                'channel' => $row->channel,
                'logo'    => $hasLogo ? ($row->logo ?? null) : null,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Display the CC Messages Audit page.
     */
    public function ccMessagesAudit()
    {
        $channelsWithLogo = $this->getActiveChannelsWithLogo();
        $channels = array_column($channelsWithLogo, 'channel');

        // Pull the most recent audit (per channel) for the cc_messages module so
        // the Audit column can show a red / amber / green button based on age.
        $lastAuditMap = AuditResult::where('module', 'cc_messages')
            ->selectRaw('channel, MAX(GREATEST(COALESCE(audit_date, "1970-01-01"), DATE(created_at))) AS last_at')
            ->groupBy('channel')
            ->pluck('last_at', 'channel')
            ->toArray();

        // Decorate channels with their last audit date (or null if never audited)
        $channelsWithLogo = array_map(function ($row) use ($lastAuditMap) {
            $row['last_audited_at'] = $lastAuditMap[$row['channel']] ?? null;
            return $row;
        }, $channelsWithLogo);

        $isAuditAdmin = $this->isAuditAdmin();

        return view('audit-master.cc-messages-audit', compact(
            'channels', 'channelsWithLogo', 'isAuditAdmin'
        ));
    }

    /**
     * Display the CC Return Audit page.
     */
    public function ccReturnAudit()
    {
        $channels = $this->getActiveChannels();

        return view('audit-master.cc-return-audit', compact('channels'));
    }

    /**
     * Display the CC Replacement Audit page.
     */
    public function ccReplacementAudit()
    {
        $channels = $this->getActiveChannels();

        return view('audit-master.cc-replacement-audit', compact('channels'));
    }

    /**
     * Display the CC Shipping Audit page.
     */
    public function ccShippingAudit()
    {
        $channelsWithLogo = $this->getActiveChannelsWithLogo();
        $channels = array_column($channelsWithLogo, 'channel');

        // Per-channel last audit timestamp so the Audit button can colour itself
        // (red / blue / green) based on age — same UX as cc-messages-audit.
        $lastAuditMap = AuditResult::where('module', 'cc_shipping')
            ->selectRaw('channel, MAX(GREATEST(COALESCE(audit_date, "1970-01-01"), DATE(created_at))) AS last_at')
            ->groupBy('channel')
            ->pluck('last_at', 'channel')
            ->toArray();

        $channelsWithLogo = array_map(function ($row) use ($lastAuditMap) {
            $row['last_audited_at'] = $lastAuditMap[$row['channel']] ?? null;
            return $row;
        }, $channelsWithLogo);

        $isAuditAdmin = $this->isAuditAdmin();

        return view('audit-master.cc-shipping-audit', compact(
            'channels', 'channelsWithLogo', 'isAuditAdmin'
        ));
    }

    // =====================================================================
    //  Audit System APIs (used by the audit modal on every audit page)
    // =====================================================================

    /**
     * Return the dynamic audit parameter list + grade bands for a module
     * (and optionally a channel).
     *
     * GET /audit-master/parameters?module=cc_messages&channel=Amazon
     */
    public function getAuditConfig(Request $request): JsonResponse
    {
        $module  = (string) $request->input('module', 'cc_messages');
        $channel = (string) $request->input('channel', '');

        $params = AuditParameter::active()
            ->forModule($module)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id', 'code', 'label', 'description', 'category',
                'max_score', 'weight', 'is_critical', 'sort_order',
            ]);

        // Module-scoped grade bands (falls back to global rows when the module
        // has no overrides of its own).
        $grades = AuditGrade::bandsForModule($module)
            ->map(fn ($g) => [
                'grade'       => $g->grade,
                'min_score'   => (float) $g->min_score,
                'max_score'   => (float) $g->max_score,
                'color'       => $g->color,
                'description' => $g->description,
            ])
            ->values();

        // Latest audit for this channel (so the modal can pre-fill)
        $latest = null;
        if ($channel !== '') {
            $latest = AuditResult::where('module', $module)
                ->where('channel', $channel)
                ->latest('id')
                ->first(['id', 'total_score', 'grade', 'has_critical_failure', 'created_at', 'audit_date']);
        }

        return response()->json([
            'success'    => true,
            'module'     => $module,
            'channel'    => $channel,
            'parameters' => $params,
            'grades'     => $grades,
            'latest'     => $latest,
            'critical_fail_reasons' => $this->defaultCriticalFailReasons($module),
            'is_admin'   => $this->isAuditAdmin(),
        ]);
    }

    // =====================================================================
    //  Admin endpoints — gated to ADMIN_EMAILS only
    // =====================================================================

    /**
     * Common validation rules for create / update of an audit parameter.
     */
    private function parameterRules(?int $ignoreId = null): array
    {
        return [
            'module'      => 'required|string|max:64',
            'code'        => 'nullable|string|max:80|regex:/^[a-z0-9_]+$/',
            'label'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'required|in:core_qa,channel_compliance',
            'max_score'   => 'required|integer|min:1|max:100',
            'weight'      => 'required|numeric|min:0|max:99.99',
            'is_critical' => 'nullable|boolean',
            'is_active'   => 'nullable|boolean',
            'sort_order'  => 'nullable|integer|min:0|max:65535',
        ];
    }

    /**
     * POST /audit-master/parameters/manage
     */
    public function storeParameter(Request $request): JsonResponse
    {
        if (! $this->isAuditAdmin()) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $data = $request->validate($this->parameterRules());

        // Auto-generate a unique code from the label if not supplied.
        if (empty($data['code'])) {
            $data['code'] = $this->generateUniqueParameterCode($data['module'], $data['label']);
        } else {
            // ensure unique within (module, code)
            $exists = AuditParameter::where('module', $data['module'])
                ->where('code', $data['code'])->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A parameter with this code already exists for this module.',
                ], 422);
            }
        }

        $data['is_critical'] = $this->boolish($data['is_critical'] ?? false);
        $data['is_active']   = $this->boolish($data['is_active'] ?? true);
        if (! isset($data['sort_order'])) {
            $data['sort_order'] = (int) (AuditParameter::where('module', $data['module'])
                ->where('category', $data['category'])->max('sort_order') ?? 0) + 1;
        }

        $param = AuditParameter::create($data);

        return response()->json(['success' => true, 'parameter' => $param]);
    }

    /**
     * PUT /audit-master/parameters/manage/{id}
     */
    public function updateParameter(Request $request, int $id): JsonResponse
    {
        if (! $this->isAuditAdmin()) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $param = AuditParameter::find($id);
        if (! $param) {
            return response()->json(['success' => false, 'message' => 'Parameter not found.'], 404);
        }

        $data = $request->validate($this->parameterRules($id));

        // If code changed, ensure it stays unique
        if (!empty($data['code']) && $data['code'] !== $param->code) {
            $taken = AuditParameter::where('module', $data['module'])
                ->where('code', $data['code'])
                ->where('id', '!=', $id)
                ->exists();
            if ($taken) {
                return response()->json([
                    'success' => false,
                    'message' => 'A parameter with this code already exists for this module.',
                ], 422);
            }
        }

        $data['is_critical'] = $this->boolish($data['is_critical'] ?? $param->is_critical);
        $data['is_active']   = $this->boolish($data['is_active']   ?? $param->is_active);

        $param->update($data);

        return response()->json(['success' => true, 'parameter' => $param->fresh()]);
    }

    /**
     * DELETE /audit-master/parameters/manage/{id}
     *
     * To preserve historical audit_result_items references, this performs a
     * soft archive (is_active = false). Admins can re-enable later via update.
     */
    public function destroyParameter(int $id): JsonResponse
    {
        if (! $this->isAuditAdmin()) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $param = AuditParameter::find($id);
        if (! $param) {
            return response()->json(['success' => false, 'message' => 'Parameter not found.'], 404);
        }

        $param->is_active = false;
        $param->save();

        return response()->json(['success' => true, 'message' => 'Parameter archived.']);
    }

    /**
     * Build a unique code (lowercase snake_case) from a label.
     */
    private function generateUniqueParameterCode(string $module, string $label): string
    {
        $base = strtolower(trim($label));
        $base = preg_replace('/[^a-z0-9]+/', '_', $base);
        $base = trim($base, '_');
        if ($base === '') $base = 'param';
        if (strlen($base) > 60) $base = substr($base, 0, 60);

        $code = $base;
        $i = 1;
        while (AuditParameter::where('module', $module)->where('code', $code)->exists()) {
            $i++;
            $code = $base . '_' . $i;
            if (strlen($code) > 80) {
                $code = substr($base, 0, 80 - strlen('_' . $i)) . '_' . $i;
            }
        }
        return $code;
    }

    /**
     * Persist a new audit (with item scores and optional attachments).
     *
     * POST /audit-master/audits
     */
    public function storeAudit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module'                  => 'required|string|max:64',
            'channel'                 => 'required|string|max:191',
            'executive_name'          => 'nullable|string|max:191',
            'message_reference'       => 'nullable|string|max:500',
            'audit_date'              => 'nullable|date',
            'auditor_notes'           => 'nullable|string',
            'bonus_points'            => 'nullable|numeric|min:0|max:20',
            'critical_failure_reasons'=> 'nullable|string',
            'items'                   => 'required|array|min:1',
            'items.*.id'              => 'required|integer',
            'items.*.score'           => 'nullable|numeric|min:0',
            'items.*.is_critical_failed' => 'nullable',
            'items.*.remarks'         => 'nullable|string',
            'attachments.*'           => 'nullable|file|max:5120', // 5MB each
        ]);

        $module      = $validated['module'];
        $channel     = $validated['channel'];
        $bonusPoints = (float) ($validated['bonus_points'] ?? 0);

        // Look up channel_master id (best effort; not strictly required)
        $channelMaster = ChannelMaster::where('channel', $channel)->first();

        // Pull all referenced parameters in one go and key them by id
        $paramIds = collect($validated['items'])->pluck('id')->unique()->values();
        $params   = AuditParameter::whereIn('id', $paramIds)->get()->keyBy('id');

        // ---- Calculate scores per category ----
        $catTotals = [
            'core_qa' => ['raw' => 0.0, 'max' => 0.0],
            'channel_compliance' => ['raw' => 0.0, 'max' => 0.0],
        ];

        $hasCriticalFailure = false;
        $criticalReasons    = [];
        $itemsToInsert      = [];

        foreach ($validated['items'] as $row) {
            $param = $params->get((int) $row['id']);
            if (! $param) {
                continue;
            }

            $score        = max(0.0, (float) ($row['score'] ?? 0));
            $score        = min($score, (float) $param->max_score);
            $weight       = (float) $param->weight;
            $maxForParam  = (float) $param->max_score;
            $category     = in_array($param->category, ['core_qa', 'channel_compliance'], true)
                ? $param->category
                : 'core_qa';

            $catTotals[$category]['raw'] += $score * $weight;
            $catTotals[$category]['max'] += $maxForParam * $weight;

            $isCritical       = (bool) $param->is_critical;
            $isCriticalFailed = $this->boolish($row['is_critical_failed'] ?? false)
                || ($isCritical && $score < ($maxForParam * 0.5));

            if ($isCriticalFailed) {
                $hasCriticalFailure = true;
                $criticalReasons[]  = $param->label;
            }

            $itemsToInsert[] = [
                'audit_parameter_id' => $param->id,
                'parameter_code'     => $param->code,
                'parameter_label'    => $param->label,
                'category'           => $category,
                'score'              => $score,
                'max_score'          => $maxForParam,
                'weight'             => $weight,
                'is_critical'        => $isCritical,
                'is_critical_failed' => $isCriticalFailed,
                'remarks'            => $row['remarks'] ?? null,
            ];
        }

        // Convert to 0-100 within each category, then weight 70/30
        $coreQaPct      = $catTotals['core_qa']['max'] > 0
            ? ($catTotals['core_qa']['raw'] / $catTotals['core_qa']['max']) * 100
            : 0;
        $compliancePct  = $catTotals['channel_compliance']['max'] > 0
            ? ($catTotals['channel_compliance']['raw'] / $catTotals['channel_compliance']['max']) * 100
            : 0;

        // Final 0-100 weighted score
        $weightedTotal = ($coreQaPct * 0.70) + ($compliancePct * 0.30);

        // Apply bonus, clamp to 0-120
        $totalScore = min(120, max(0, $weightedTotal + $bonusPoints));

        // Critical failure forces the bottom band
        if ($hasCriticalFailure) {
            $grade = 'F';
        } else {
            $band = AuditGrade::forScore($totalScore, $module);
            $grade = $band->grade ?? 'F';
        }

        // Merge auto-detected critical reasons with whatever the auditor typed
        $criticalReasonsText = trim((string) ($validated['critical_failure_reasons'] ?? ''));
        if (!empty($criticalReasons)) {
            $autoText = 'Auto-flagged: ' . implode(', ', array_unique($criticalReasons));
            $criticalReasonsText = $criticalReasonsText !== ''
                ? $criticalReasonsText . "\n" . $autoText
                : $autoText;
        }

        $audit = DB::transaction(function () use (
            $request, $validated, $module, $channel, $channelMaster,
            $coreQaPct, $compliancePct, $bonusPoints, $totalScore, $grade,
            $hasCriticalFailure, $criticalReasonsText, $itemsToInsert
        ) {
            $audit = AuditResult::create([
                'module'                  => $module,
                'channel'                 => $channel,
                'channel_master_id'       => $channelMaster->id ?? null,
                'executive_name'          => $validated['executive_name'] ?? null,
                'auditor_id'              => Auth::id(),
                'message_reference'       => $validated['message_reference'] ?? null,
                'audit_date'              => $validated['audit_date'] ?? now()->toDateString(),
                'core_qa_score'           => round($coreQaPct, 2),
                'channel_compliance_score' => round($compliancePct, 2),
                'bonus_points'            => round($bonusPoints, 2),
                'total_score'             => round($totalScore, 2),
                'grade'                   => $grade,
                'has_critical_failure'    => $hasCriticalFailure,
                'critical_failure_reasons' => $criticalReasonsText !== '' ? $criticalReasonsText : null,
                'auditor_notes'           => $validated['auditor_notes'] ?? null,
                'status'                  => 'submitted',
            ]);

            $now = now();
            $rows = array_map(function ($item) use ($audit, $now) {
                return $item + [
                    'audit_result_id' => $audit->id,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }, $itemsToInsert);
            AuditResultItem::insert($rows);

            // Save attachments (multipart files under attachments[])
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if (!$file->isValid()) continue;

                    $ext  = $file->getClientOriginalExtension() ?: 'bin';
                    $name = time() . '_' . uniqid() . '.' . $ext;
                    $file->storeAs('public/audit-attachments', $name);

                    AuditAttachment::create([
                        'audit_result_id' => $audit->id,
                        'file_path'       => 'audit-attachments/' . $name,
                        'original_name'   => $file->getClientOriginalName(),
                        'mime_type'       => $file->getClientMimeType(),
                        'size_bytes'      => $file->getSize(),
                    ]);
                }
            }

            return $audit;
        });

        return response()->json([
            'success' => true,
            'message' => 'Audit saved successfully',
            'audit'   => [
                'id'                       => $audit->id,
                'channel'                  => $audit->channel,
                'module'                   => $audit->module,
                'core_qa_score'            => (float) $audit->core_qa_score,
                'channel_compliance_score' => (float) $audit->channel_compliance_score,
                'bonus_points'             => (float) $audit->bonus_points,
                'total_score'              => (float) $audit->total_score,
                'grade'                    => $audit->grade,
                'has_critical_failure'     => (bool) $audit->has_critical_failure,
                'audit_date'               => optional($audit->audit_date)->toDateString(),
                'created_at'               => $audit->created_at->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Audit history for a channel + module.
     *
     * GET /audit-master/audits?module=cc_messages&channel=Amazon
     */
    public function getAuditHistory(Request $request): JsonResponse
    {
        $module  = (string) $request->input('module', 'cc_messages');
        $channel = (string) $request->input('channel', '');
        $limit   = (int) $request->input('limit', 25);

        $query = AuditResult::where('module', $module);
        if ($channel !== '') {
            $query->where('channel', $channel);
        }

        $rows = $query->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id', 'channel', 'executive_name', 'message_reference',
                'core_qa_score', 'channel_compliance_score', 'bonus_points',
                'total_score', 'grade', 'has_critical_failure',
                'audit_date', 'created_at',
            ]);

        return response()->json([
            'success' => true,
            'audits'  => $rows,
        ]);
    }

    /**
     * Single audit detail with items + attachments.
     *
     * GET /audit-master/audits/{id}
     */
    public function showAudit(int $id): JsonResponse
    {
        $audit = AuditResult::with(['items', 'attachments'])->find($id);
        if (! $audit) {
            return response()->json(['success' => false, 'message' => 'Audit not found'], 404);
        }

        $audit->attachments->each(function ($a) {
            $a->url = '/storage/' . ltrim($a->file_path, '/');
        });

        return response()->json([
            'success' => true,
            'audit'   => $audit,
        ]);
    }

    /**
     * Agent-wise KPI summary + score history (for the top-of-page panel).
     *
     * GET /audit-master/agent-kpis?module=cc_messages&days=90
     */
    public function getAgentKpis(Request $request): JsonResponse
    {
        $module = (string) $request->input('module', 'cc_messages');
        $days   = max(7, min(365, (int) $request->input('days', 90)));
        $from   = now()->subDays($days)->startOfDay();

        $query = AuditResult::where('module', $module)
            ->whereNotNull('executive_name')
            ->where('executive_name', '!=', '')
            ->where(function ($q) use ($from) {
                $q->where('audit_date', '>=', $from->toDateString())
                  ->orWhere('created_at', '>=', $from);
            })
            ->orderBy('executive_name')
            ->orderBy('audit_date')
            ->orderBy('id');

        $rows = $query->get([
            'id', 'channel', 'executive_name', 'message_reference',
            'core_qa_score', 'channel_compliance_score', 'bonus_points',
            'total_score', 'grade', 'has_critical_failure',
            'critical_failure_reasons', 'auditor_notes',
            'audit_date', 'created_at',
        ]);

        // Group by agent and build per-agent summary + history.
        $agents = [];
        foreach ($rows as $r) {
            $name = trim((string) $r->executive_name);
            if ($name === '') continue;

            if (! isset($agents[$name])) {
                $agents[$name] = [
                    'name'              => $name,
                    'total_audits'      => 0,
                    'critical_failures' => 0,
                    'sum_score'         => 0.0,
                    'last_score'        => null,
                    'last_grade'        => null,
                    'last_audit_date'   => null,
                    'best_score'        => null,
                    'worst_score'       => null,
                    'history'           => [],
                ];
            }

            $score = (float) $r->total_score;
            $date  = $r->audit_date
                ? $r->audit_date->toDateString()
                : ($r->created_at ? $r->created_at->toDateString() : null);

            $agents[$name]['total_audits']++;
            $agents[$name]['sum_score'] += $score;
            if ($r->has_critical_failure) {
                $agents[$name]['critical_failures']++;
            }
            if ($agents[$name]['best_score']  === null || $score > $agents[$name]['best_score'])  $agents[$name]['best_score']  = $score;
            if ($agents[$name]['worst_score'] === null || $score < $agents[$name]['worst_score']) $agents[$name]['worst_score'] = $score;

            // history rows arrive in chronological order
            $agents[$name]['history'][] = [
                'id'          => $r->id,
                'date'        => $date,
                'created_at'  => $r->created_at?->toDateTimeString(),
                'channel'     => $r->channel,
                'reference'   => $r->message_reference,
                'core_qa'     => (float) $r->core_qa_score,
                'compliance'  => (float) $r->channel_compliance_score,
                'bonus'       => (float) $r->bonus_points,
                'score'       => $score,
                'grade'       => $r->grade,
                'critical'    => (bool) $r->has_critical_failure,
                'critical_reasons' => $r->critical_failure_reasons,
                'remarks'     => $r->auditor_notes,
            ];

            // Latest by sort order (already chronologically last when loop ends)
            $agents[$name]['last_score']      = $score;
            $agents[$name]['last_grade']      = $r->grade;
            $agents[$name]['last_audit_date'] = $date;
        }

        // Finalise: average score, sort agents by avg desc
        $agents = array_values(array_map(function ($a) {
            $a['avg_score']  = $a['total_audits'] > 0
                ? round($a['sum_score'] / $a['total_audits'], 2)
                : 0;
            unset($a['sum_score']);
            return $a;
        }, $agents));

        usort($agents, fn ($x, $y) => $y['avg_score'] <=> $x['avg_score']);

        // Roll-up across all agents
        $summary = [
            'total_agents'      => count($agents),
            'total_audits'      => array_sum(array_column($agents, 'total_audits')),
            'critical_failures' => array_sum(array_column($agents, 'critical_failures')),
            'avg_score'         => count($agents) > 0
                ? round(array_sum(array_column($agents, 'avg_score')) / count($agents), 2)
                : 0,
            'window_days'       => $days,
        ];

        return response()->json([
            'success' => true,
            'module'  => $module,
            'summary' => $summary,
            'agents'  => $agents,
        ]);
    }

    private function defaultCriticalFailReasons(string $module = 'cc_messages'): array
    {
        if ($module === 'cc_shipping') {
            return [
                'Wrong shipping carrier',
                'Wrong shipping address',
                'Wrong package weight (charge adjustment)',
                'Wrong product shipped',
                'Duplicate shipment created',
                'Invalid / missing tracking upload',
                'SLA breach (label generated late)',
                'International compliance violation',
                'Hazardous goods mishandled',
                'Customs documentation missing',
            ];
        }

        // Default: cc_messages and other modules
        return [
            'No response within 24 hours',
            'Marketplace policy violation',
            'Rude or unprofessional communication',
            'False promises made to customer',
            'Review manipulation attempt',
            'Confidential data leak',
        ];
    }

    private function boolish($v): bool
    {
        if (is_bool($v)) return $v;
        if (is_numeric($v)) return (int) $v === 1;
        return in_array(strtolower((string) $v), ['1', 'true', 'on', 'yes'], true);
    }
}
