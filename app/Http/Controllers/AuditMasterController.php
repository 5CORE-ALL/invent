<?php

namespace App\Http\Controllers;

use App\Models\AuditAttachment;
use App\Models\AuditGrade;
use App\Models\AuditParameter;
use App\Models\AuditResult;
use App\Models\AuditResultItem;
use App\Models\CcMessageChannelNext;
use App\Models\CcMessageChecklist;
use App\Models\CcReturnsChannelLink;
use App\Models\CcReturnsChannelNext;
use App\Models\CcReturnsChecklist;
use App\Models\CcShippingChannelLink;
use App\Models\CcShippingChannelNext;
use App\Models\CcShippingChecklist;
use App\Models\CcShippingReturnsChannelNext;
use App\Models\CcShippingReturnsChecklist;
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
     * Emails allowed to edit the SOP (Standard Operating Procedure) HTML
     * content shown by the SOP button on each audit page.
     * Comparison is case-insensitive.
     */
    private const SOP_ADMIN_EMAILS = [
        'president@5core.com',
        'software5@5core.com',
    ];

    /**
     * Email(s) allowed to edit the per-channel "Next" priority value (1..9)
     * on the /customer-care/cc-messages-returns page. Comparison is
     * case-insensitive.
     */
    private const NEXT_EDITOR_EMAILS = [
        'mgr-operations@5core.com',
        'president@5core.com',
    ];

    /**
     * Per-page table set used by the shared CC checklist page builder.
     * Adding a third workflow (e.g. "warehouse") later means appending
     * one entry here + a thin controller wrapper + a new route + a clone
     * of the blade view.
     */
    private function checklistPageSet(string $page): array
    {
        $sets = [
            'messages_returns' => [
                'view'        => 'customer-care.cc-messages-returns',
                'msg_table'   => 'cc_message_checklists',
                'msg_model'   => CcMessageChecklist::class,
                'ret_table'   => 'cc_returns_checklists',
                'ret_model'   => CcReturnsChecklist::class,
                'msg_next_t'  => 'cc_message_channel_next',
                'msg_next_m'  => CcMessageChannelNext::class,
                'ret_next_t'  => 'cc_returns_channel_next',
                'ret_next_m'  => CcReturnsChannelNext::class,
            ],
            'shipping' => [
                'view'        => 'customer-care.cc-shipping',
                'msg_table'   => 'cc_shipping_checklists',
                'msg_model'   => CcShippingChecklist::class,
                'ret_table'   => 'cc_shipping_returns_checklists',
                'ret_model'   => CcShippingReturnsChecklist::class,
                'msg_next_t'  => 'cc_shipping_channel_next',
                'msg_next_m'  => CcShippingChannelNext::class,
                'ret_next_t'  => 'cc_shipping_returns_channel_next',
                'ret_next_m'  => CcShippingReturnsChannelNext::class,
                // S link (Shipping link) — per-channel, lives in its own
                // table so it stays isolated from M / H / R link storage.
                's_link_t'    => 'cc_shipping_channel_links',
                's_link_m'    => CcShippingChannelLink::class,
            ],
        ];

        return $sets[$page] ?? $sets['messages_returns'];
    }

    /**
     * Whitelist of SOP "keys" the front-end may load / save. Restricts the
     * route to known files only so a request cannot reach arbitrary storage
     * paths via the key parameter.
     */
    private const SOP_KEYS = [
        'cc_messages',
        'cc_return',
        'cc_replacement',
        'cc_shipping',
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
     * True if the currently authenticated user is allowed to edit the
     * SOP HTML content (rendered inside the SOP modal).
     */
    public function isSopAdmin(): bool
    {
        $user = Auth::user();
        if (! $user) return false;
        $email = strtolower(trim((string) ($user->email ?? '')));
        return $email !== '' && in_array($email, self::SOP_ADMIN_EMAILS, true);
    }

    /**
     * True if the currently authenticated user is allowed to edit the
     * per-channel "Next" priority value (1..9) on the CC Message & Returns
     * page.
     */
    public function canEditNextValue(): bool
    {
        $user = Auth::user();
        if (! $user) return false;
        $email = strtolower(trim((string) ($user->email ?? '')));
        return $email !== '' && in_array($email, self::NEXT_EDITOR_EMAILS, true);
    }

    /**
     * Relative path inside the "local" disk for the SOP HTML file
     * associated with the given module key.
     */
    private function sopStoragePath(string $key): string
    {
        return 'sop/' . $key . '-sop.html';
    }

    /**
     * Load the saved SOP HTML for a given module key. Returns an empty
     * string when nothing has been saved yet so the modal can render its
     * own "no content yet" placeholder.
     */
    private function loadSopHtml(string $key): string
    {
        if (! in_array($key, self::SOP_KEYS, true)) {
            return '';
        }

        $path = $this->sopStoragePath($key);
        try {
            return Storage::disk('local')->exists($path)
                ? (string) Storage::disk('local')->get($path)
                : '';
        } catch (\Throwable $e) {
            return '';
        }
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
     * Count minutes between two timestamps that fall OUTSIDE the working
     * window 06:00–18:00 (i.e., overnight hours from 18:00 to 06:00 next
     * day). Used by the "TAT" column on the CC Message & Returns page.
     *
     * Walks the range one transition at a time (at most ~2 transitions per
     * day) so it stays O(days) regardless of how long the gap is.
     */
    private function offHoursMinutesBetween(\Carbon\Carbon $start, \Carbon\Carbon $end): int
    {
        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $count  = 0;
        $cursor = $start->copy();

        // Hard safety cap so a corrupt timestamp can't loop forever.
        $maxIters = 3650; // ~10 years of half-day blocks
        $iter = 0;

        while ($cursor->lessThan($end) && $iter < $maxIters) {
            $iter++;
            $hour  = (int) $cursor->hour;
            $isOff = ($hour >= 18 || $hour < 6);

            // Find the next 06:00 / 18:00 boundary after $cursor.
            if ($hour < 6) {
                $next = $cursor->copy()->setTime(6, 0, 0);
            } elseif ($hour < 18) {
                $next = $cursor->copy()->setTime(18, 0, 0);
            } else {
                $next = $cursor->copy()->addDay()->setTime(6, 0, 0);
            }

            $blockEnd = $next->lessThan($end) ? $next : $end;
            $blockMinutes = (int) floor($cursor->diffInSeconds($blockEnd) / 60);
            if ($isOff) {
                $count += $blockMinutes;
            }
            $cursor = $blockEnd;
        }

        return $count;
    }

    /**
     * Display the "CC message & Returns" page (Customer Care sidebar group).
     *
     * Tabulator listing of active channels from channel_master with their
     * logo + the shared M link / H link from the Account Health Master
     * metric field definitions. Reuses
     * AccountHealthMasterController::definitionScopeForChannel() and the
     * `account.health.master.scope.link.save` endpoint so M/H links stay
     * in sync with the /account-health-master/tabulator page.
     */
    public function ccMessagesReturns()
    {
        return $this->renderChecklistPage('messages_returns');
    }

    /**
     * Display the "CC Shipping" page — same UI and data shape as
     * /customer-care/cc-messages-returns, but every checklist + next
     * row is read from a separate set of cc_shipping_* tables so the
     * Shipping team's data is fully isolated from the Messages /
     * Returns team's data.
     */
    public function ccShipping()
    {
        return $this->renderChecklistPage('shipping');
    }

    /**
     * Generic window-state snapshot used by both the 9AM Clear and
     * 3 PM Clear columns. Evaluated server-side so the front-end can
     * trust the times even if the user's clock is off.
     */
    private function shippingWindowState(int $fromHour, int $toHour, string $label): array
    {
        $now = \Carbon\Carbon::now(self::SHIPPING_CLEAR_TZ);
        $h   = (int) $now->hour;
        $open = $h >= $fromHour && $h < $toHour;

        return [
            'tz'          => self::SHIPPING_CLEAR_TZ,
            'from_hour'   => $fromHour,
            'to_hour'     => $toHour,
            'label'       => $label,
            'now_iso'     => $now->toIso8601String(),
            'now_label'   => $now->format('H:i'),
            'open'        => $open,
            'today_local' => $now->toDateString(),
        ];
    }

    private function nineAmClearWindowState(): array
    {
        return $this->shippingWindowState(
            self::SHIPPING_CLEAR_WINDOW_FROM,
            self::SHIPPING_CLEAR_WINDOW_TO,
            '9AM Clear'
        );
    }

    private function threePmClearWindowState(): array
    {
        return $this->shippingWindowState(
            self::SHIPPING_RETURNS_WINDOW_FROM,
            self::SHIPPING_RETURNS_WINDOW_TO,
            '3 PM Clear'
        );
    }

    /**
     * Aggregate "success" and "missed" submission counts across all
     * provided channels for the past 30 calendar days, excluding Sundays,
     * for both Shipping-page workflows. Used to feed the two summary
     * badges shown above the table.
     *
     *   - success = distinct (channel, EST-date) pairs that have AT LEAST
     *               ONE submission on that EST date.
     *   - missed  = total eligible slots minus success.
     *
     * Where total eligible slots = (active channels) × (eligible days).
     */
    private function ccShippingHistoryAggregates(array $channelIds): array
    {
        $tz    = self::SHIPPING_CLEAR_TZ;
        $today = \Carbon\Carbon::now($tz)->startOfDay();

        // Build the list of eligible EST dates: 30 calendar days back
        // (inclusive of today), excluding Sundays.
        $eligibleDates = [];
        for ($i = 0; $i < 30; $i++) {
            $d = $today->copy()->subDays($i);
            if ($d->dayOfWeek === \Carbon\Carbon::SUNDAY) continue;
            $eligibleDates[$d->toDateString()] = true;
        }

        $channelCount = count($channelIds);
        $slotsPerKind = $channelCount * count($eligibleDates);

        // Lower bound for the SQL filter. Bias by 32 hours so a submission
        // logged just before midnight EST that maps to "today" doesn't get
        // missed when stored in UTC / app TZ.
        $minSubmitted = $today->copy()->subDays(31)->setTimezone(config('app.timezone'));

        $out = [];
        $tables = [
            'nine_am_clear'  => 'cc_shipping_checklists',
            'three_pm_clear' => 'cc_shipping_returns_checklists',
        ];

        foreach ($tables as $key => $table) {
            $base = [
                'success'  => 0,
                'missed'  => $slotsPerKind,
                'total'   => $slotsPerKind,
                'days'    => count($eligibleDates),
                'channels' => $channelCount,
            ];

            if (! Schema::hasTable($table) || empty($channelIds) || empty($eligibleDates)) {
                $out[$key] = $base;
                continue;
            }

            // One query per table — pulls only the (channel_id, submitted_at)
            // pair for submissions in the recent window. We bucket in PHP
            // because timezone-aware date bucketing is tricky in MySQL.
            $rows = DB::table($table)
                ->whereIn('channel_id', $channelIds)
                ->where('submitted_at', '>=', $minSubmitted)
                ->get(['channel_id', 'submitted_at']);

            $coveredPairs = [];
            foreach ($rows as $r) {
                if (empty($r->submitted_at)) continue;
                try {
                    $estDate = \Carbon\Carbon::parse($r->submitted_at)
                        ->setTimezone($tz)
                        ->toDateString();
                    if (! isset($eligibleDates[$estDate])) continue;
                    $coveredPairs[$r->channel_id . '|' . $estDate] = true;
                } catch (\Throwable $e) {
                    // skip unparseable
                }
            }

            $successCount = count($coveredPairs);
            $out[$key] = [
                'success'  => $successCount,
                'missed'   => max(0, $slotsPerKind - $successCount),
                'total'    => $slotsPerKind,
                'days'     => count($eligibleDates),
                'channels' => $channelCount,
            ];
        }

        return $out;
    }

    /**
     * Shared page renderer. Given a page key (resolved through
     * checklistPageSet()), pulls active channels, M / H / R links,
     * latest + previous checklist submissions for both halves, the
     * per-channel "Next" priorities, and computes off-hours TAT for
     * both halves. Returns a view with the same `channelsWithLogo`
     * payload structure regardless of which page is being rendered,
     * so a single blade template (or a literal clone of it) can render
     * either page without further changes.
     */
    private function renderChecklistPage(string $page)
    {
        $set = $this->checklistPageSet($page);

        // Pull active channels with id + channel + logo. The id is needed
        // so the M/H link modal can POST channel_id to the shared
        // scope-link save endpoint.
        $hasLogo = Schema::hasColumn('channel_master', 'logo');
        $columns = ['id', 'channel'];
        if ($hasLogo) {
            $columns[] = 'logo';
        }

        $channels = ChannelMaster::where('status', 'Active')
            ->orderBy('channel')
            ->get($columns)
            ->filter(fn ($row) => !empty($row->channel))
            ->values();

        // Resolve M / H link per channel via the same scope rules used by
        // /account-health-master/tabulator. R link is per-channel in
        // cc_returns_channel_links (like S link on cc-shipping).
        $ahmController = app(\App\Http\Controllers\Channels\AccountHealthMasterController::class);
        $scopeToMLink = [];
        $scopeToHLink = [];

        // Pre-load the LATEST + second-most-recent checklist submission per
        // channel for BOTH halves so the History + TAT columns can render
        // without N+1 queries. All four table names come from $set so the
        // exact same code drives the Shipping page too.
        $latestChecklistMap = [];
        $prevChecklistMap   = [];
        $latestReturnsMap   = [];
        $prevReturnsMap     = [];
        $nextValueMap       = [];
        $nextReturnsValueMap = [];
        $channelIds = $channels->pluck('id')->all();

        $msgTable = $set['msg_table'];
        $msgModel = $set['msg_model'];
        if (Schema::hasTable($msgTable) && !empty($channelIds)) {
            $latestIds = $msgModel::query()
                ->whereIn('channel_id', $channelIds)
                ->selectRaw('MAX(id) AS id')
                ->groupBy('channel_id')
                ->pluck('id')
                ->all();

            if (!empty($latestIds)) {
                $latestChecklistMap = $msgModel::query()
                    ->whereIn('id', $latestIds)
                    ->get()
                    ->keyBy('channel_id');

                $prevIds = DB::table($msgTable)
                    ->whereIn('channel_id', $channelIds)
                    ->whereNotIn('id', $latestIds)
                    ->selectRaw('channel_id, MAX(id) AS id')
                    ->groupBy('channel_id')
                    ->pluck('id', 'channel_id')
                    ->toArray();

                if (!empty($prevIds)) {
                    $prevChecklistMap = $msgModel::query()
                        ->whereIn('id', array_values($prevIds))
                        ->get()
                        ->keyBy('channel_id');
                }
            }
        }

        $retTable = $set['ret_table'];
        $retModel = $set['ret_model'];
        if (Schema::hasTable($retTable) && !empty($channelIds)) {
            $latestReturnsIds = $retModel::query()
                ->whereIn('channel_id', $channelIds)
                ->selectRaw('MAX(id) AS id')
                ->groupBy('channel_id')
                ->pluck('id')
                ->all();

            if (!empty($latestReturnsIds)) {
                $latestReturnsMap = $retModel::query()
                    ->whereIn('id', $latestReturnsIds)
                    ->get()
                    ->keyBy('channel_id');

                $prevReturnsIds = DB::table($retTable)
                    ->whereIn('channel_id', $channelIds)
                    ->whereNotIn('id', $latestReturnsIds)
                    ->selectRaw('channel_id, MAX(id) AS id')
                    ->groupBy('channel_id')
                    ->pluck('id', 'channel_id')
                    ->toArray();

                if (!empty($prevReturnsIds)) {
                    $prevReturnsMap = $retModel::query()
                        ->whereIn('id', array_values($prevReturnsIds))
                        ->get()
                        ->keyBy('channel_id');
                }
            }
        }

        $msgNextTable = $set['msg_next_t'];
        $msgNextModel = $set['msg_next_m'];
        if (Schema::hasTable($msgNextTable) && !empty($channelIds)) {
            $nextValueMap = $msgNextModel::query()
                ->whereIn('channel_id', $channelIds)
                ->get()
                ->keyBy('channel_id');
        }

        $retNextTable = $set['ret_next_t'];
        $retNextModel = $set['ret_next_m'];
        if (Schema::hasTable($retNextTable) && !empty($channelIds)) {
            $nextReturnsValueMap = $retNextModel::query()
                ->whereIn('channel_id', $channelIds)
                ->get()
                ->keyBy('channel_id');
        }

        // Per-page custom link map (currently only the Shipping page uses
        // this — it stores its own S link in cc_shipping_channel_links).
        $sLinkMap = [];
        $sLinkTable = $set['s_link_t'] ?? null;
        $sLinkModel = $set['s_link_m'] ?? null;
        if ($sLinkTable && $sLinkModel && Schema::hasTable($sLinkTable) && !empty($channelIds)) {
            $sLinkMap = $sLinkModel::query()
                ->whereIn('channel_id', $channelIds)
                ->get()
                ->keyBy('channel_id');
        }

        $rLinkMap = [];
        if (Schema::hasTable('cc_returns_channel_links') && ! empty($channelIds)) {
            $rLinkMap = CcReturnsChannelLink::query()
                ->whereIn('channel_id', $channelIds)
                ->get()
                ->keyBy('channel_id');
        }

        $rows = $channels->map(function (ChannelMaster $c) use (
            $ahmController, $hasLogo,
            &$scopeToMLink, &$scopeToHLink,
            $latestChecklistMap, $prevChecklistMap,
            $latestReturnsMap,   $prevReturnsMap,
            $nextValueMap, $nextReturnsValueMap,
            $sLinkMap, $rLinkMap
        ) {
            $scope = $ahmController->definitionScopeForChannel($c);

            if (! array_key_exists($scope, $scopeToMLink)) {
                $scopeToMLink[$scope] = \App\Models\AccountHealthMetricFieldDefinition::query()
                    ->where('definition_scope', $scope)
                    ->whereNotNull('m_link')
                    ->where('m_link', '!=', '')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->value('m_link');
            }
            if (! array_key_exists($scope, $scopeToHLink)) {
                $scopeToHLink[$scope] = \App\Models\AccountHealthMetricFieldDefinition::query()
                    ->where('definition_scope', $scope)
                    ->whereNotNull('h_link')
                    ->where('h_link', '!=', '')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->value('h_link');
            }
            $latest = $latestChecklistMap[$c->id] ?? null;
            $latestArr = null;
            if ($latest) {
                $latestArr = [
                    'id'                            => $latest->id,
                    'user_name'                     => $latest->user_name,
                    'submitted_at'                  => optional($latest->submitted_at)->toIso8601String(),
                    'messages_resolved'             => (bool) $latest->messages_resolved,
                    // returns_resolved is a legacy column kept only on
                    // cc_message_checklists for back-compat; other tables
                    // don't carry it.
                    'returns_resolved'              => isset($latest->returns_resolved) ? (bool) $latest->returns_resolved : false,
                    'unresolved_messages_followup'  => (bool) $latest->unresolved_messages_followup,
                    'activity_documented'           => (bool) $latest->activity_documented,
                    // extra_check / extra_check_2 only present on
                    // cc_shipping_*; default to false on other tables.
                    'extra_check'                   => isset($latest->extra_check)   ? (bool) $latest->extra_check   : false,
                    'extra_check_2'                 => isset($latest->extra_check_2) ? (bool) $latest->extra_check_2 : false,
                ];
            }

            $latestReturns = $latestReturnsMap[$c->id] ?? null;
            $latestReturnsArr = null;
            if ($latestReturns) {
                $latestReturnsArr = [
                    'id'                            => $latestReturns->id,
                    'user_name'                     => $latestReturns->user_name,
                    'submitted_at'                  => optional($latestReturns->submitted_at)->toIso8601String(),
                    'messages_resolved'             => (bool) $latestReturns->messages_resolved,
                    'unresolved_messages_followup'  => (bool) $latestReturns->unresolved_messages_followup,
                    'activity_documented'           => (bool) $latestReturns->activity_documented,
                    'extra_check'                   => isset($latestReturns->extra_check)   ? (bool) $latestReturns->extra_check   : false,
                    'extra_check_2'                 => isset($latestReturns->extra_check_2) ? (bool) $latestReturns->extra_check_2 : false,
                ];
            }

            $nextRow = $nextValueMap[$c->id] ?? null;
            $nextValue = $nextRow ? (int) $nextRow->next_value : null;
            if ($nextValue !== null && ($nextValue < 1 || $nextValue > 9)) {
                $nextValue = null;
            }

            $nextReturnsRow = $nextReturnsValueMap[$c->id] ?? null;
            $nextReturnsValue = $nextReturnsRow ? (int) $nextReturnsRow->next_value : null;
            if ($nextReturnsValue !== null && ($nextReturnsValue < 1 || $nextReturnsValue > 9)) {
                $nextReturnsValue = null;
            }

            // TAT (Turn-Around-Time) for the Messages workflow:
            // off-hours minutes between the previous Messages submission
            // and the latest one. "Off-hours" = outside 06:00–18:00.
            // null when there's fewer than 2 submissions for this channel.
            $tatMinutes = null;
            $prev = $prevChecklistMap[$c->id] ?? null;
            if ($latest && $prev && $latest->submitted_at && $prev->submitted_at) {
                $tatMinutes = $this->offHoursMinutesBetween(
                    \Carbon\Carbon::parse($prev->submitted_at),
                    \Carbon\Carbon::parse($latest->submitted_at)
                );
            }

            // Same TAT computation for the Returns workflow.
            $tatReturnsMinutes = null;
            $prevReturns = $prevReturnsMap[$c->id] ?? null;
            if ($latestReturns && $prevReturns
                && $latestReturns->submitted_at && $prevReturns->submitted_at) {
                $tatReturnsMinutes = $this->offHoursMinutesBetween(
                    \Carbon\Carbon::parse($prevReturns->submitted_at),
                    \Carbon\Carbon::parse($latestReturns->submitted_at)
                );
            }

            $sLinkRow = $sLinkMap[$c->id] ?? null;
            $sLinkValue = $sLinkRow ? (trim((string) $sLinkRow->s_link) ?: null) : null;

            $rLinkRow = $rLinkMap[$c->id] ?? null;
            $rLinkValue = $rLinkRow ? (trim((string) $rLinkRow->r_link) ?: null) : null;

            return [
                'id'                       => $c->id,
                'channel'                  => $c->channel,
                'logo'                     => $hasLogo ? ($c->logo ?? null) : null,
                'm_link'                   => $scopeToMLink[$scope] ?: null,
                'h_link'                   => $scopeToHLink[$scope] ?: null,
                'r_link'                   => $rLinkValue,
                's_link'                   => $sLinkValue,
                'latest_checklist'         => $latestArr,
                'latest_returns_checklist' => $latestReturnsArr,
                'next_value'               => $nextValue,
                'next_returns_value'       => $nextReturnsValue,
                'tat_minutes'              => $tatMinutes,
                'tat_returns_minutes'      => $tatReturnsMinutes,
            ];
        })->values()->toArray();

        return view($set['view'], [
            'channelsWithLogo'   => $rows,
            'canEditNext'        => $this->canEditNextValue(),
            // Shipping page surfaces the 9AM Clear (Messages-side) and
            // 3 PM Clear (Returns-side) window states so the submit modal
            // can show "open" / "closed" and disable Submit accordingly.
            // Null on other pages.
            'nineAmClearWindow'  => $page === 'shipping' ? $this->nineAmClearWindowState()  : null,
            'threePmClearWindow' => $page === 'shipping' ? $this->threePmClearWindowState() : null,
            // 30-day (ex-Sundays) success / missed totals per workflow —
            // feeds the two summary badges shown above the Shipping table.
            'shippingHistoryAgg' => $page === 'shipping'
                ? $this->ccShippingHistoryAggregates($channelIds)
                : null,
        ]);
    }

    /**
     * Save the per-channel "Next" priority value (1..9). Restricted to
     * NEXT_EDITOR_EMAILS — everybody else gets a 403.
     */
    public function storeCcNextValue(Request $request)
    {
        if (! $this->canEditNextValue()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to edit the Next value.',
            ], 403);
        }

        $validated = $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'next_value' => 'nullable|integer|min:1|max:9',
        ]);

        $user = Auth::user();
        $userName = null;
        if ($user) {
            $name = trim((string) ($user->name ?? ''));
            if ($name === '') {
                $email = (string) ($user->email ?? '');
                $name = $email !== '' ? explode('@', $email)[0] : ('User #' . $user->id);
            }
            $userName = $name;
        }

        $row = CcMessageChannelNext::updateOrCreate(
            ['channel_id' => (int) $validated['channel_id']],
            [
                'next_value'         => array_key_exists('next_value', $validated) ? $validated['next_value'] : null,
                'updated_by_user_id' => Auth::id(),
                'updated_by_name'    => $userName,
            ]
        );

        return response()->json([
            'success' => true,
            'row' => [
                'channel_id'      => $row->channel_id,
                'next_value'      => $row->next_value !== null ? (int) $row->next_value : null,
                'updated_by_name' => $row->updated_by_name,
                'updated_at'      => optional($row->updated_at)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Save the per-channel "R Next" priority value (1..9) — drives the
     * freshness window for the R Status icon (Returns checklist). Same
     * permission gate as storeCcNextValue: only NEXT_EDITOR_EMAILS.
     */
    public function storeCcReturnsNextValue(Request $request)
    {
        if (! $this->canEditNextValue()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to edit the R Next value.',
            ], 403);
        }

        $validated = $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'next_value' => 'nullable|integer|min:1|max:9',
        ]);

        $user = Auth::user();
        $userName = null;
        if ($user) {
            $name = trim((string) ($user->name ?? ''));
            if ($name === '') {
                $email = (string) ($user->email ?? '');
                $name = $email !== '' ? explode('@', $email)[0] : ('User #' . $user->id);
            }
            $userName = $name;
        }

        $row = CcReturnsChannelNext::updateOrCreate(
            ['channel_id' => (int) $validated['channel_id']],
            [
                'next_value'         => array_key_exists('next_value', $validated) ? $validated['next_value'] : null,
                'updated_by_user_id' => Auth::id(),
                'updated_by_name'    => $userName,
            ]
        );

        return response()->json([
            'success' => true,
            'row' => [
                'channel_id'      => $row->channel_id,
                'next_value'      => $row->next_value !== null ? (int) $row->next_value : null,
                'updated_by_name' => $row->updated_by_name,
                'updated_at'      => optional($row->updated_at)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Save the per-channel R link (Returns) — one URL per channel, same model
     * as S link on cc-shipping (cc_shipping_channel_links).
     */
    public function storeCcRLink(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'value'      => 'nullable|string|max:2048',
        ]);

        if (! Schema::hasTable('cc_returns_channel_links')) {
            return response()->json([
                'success' => false,
                'message' => 'R link table is not available yet — run migrations.',
            ], 500);
        }

        $channel = ChannelMaster::query()->findOrFail((int) $request->input('channel_id'));

        $raw = $request->input('value');
        $link = $raw === null ? null : trim((string) $raw);
        if ($link === '') {
            $link = null;
        }

        $user = Auth::user();
        $userName = null;
        if ($user) {
            $name = trim((string) ($user->name ?? ''));
            if ($name === '') {
                $email = (string) ($user->email ?? '');
                $name = $email !== '' ? explode('@', $email)[0] : ('User #' . $user->id);
            }
            $userName = $name;
        }

        $row = CcReturnsChannelLink::updateOrCreate(
            ['channel_id' => $channel->id],
            [
                'r_link'             => $link,
                'updated_by_user_id' => Auth::id(),
                'updated_by_name'    => $userName,
            ]
        );

        return response()->json([
            'success'    => true,
            'channel_id' => $row->channel_id,
            'field'      => 'r_link',
            'value'      => $row->r_link,
        ]);
    }

    /**
     * Persist a single submission of the per-channel "CC Message & Returns"
     * checklist. Returns the freshly stored row so the front-end can update
     * the "History" column in place without reloading.
     */
    public function storeCcChecklist(Request $request)
    {
        $validated = $request->validate([
            'channel_id'                   => 'required|integer|exists:channel_master,id',
            'messages_resolved'            => 'sometimes|boolean',
            'returns_resolved'             => 'sometimes|boolean',
            'unresolved_messages_followup' => 'sometimes|boolean',
            'activity_documented'          => 'sometimes|boolean',
            'notes'                        => 'nullable|string|max:2000',
        ]);

        $channel = ChannelMaster::query()->findOrFail((int) $validated['channel_id']);
        $user = Auth::user();
        $userName = null;
        if ($user) {
            $name = trim((string) ($user->name ?? ''));
            if ($name === '') {
                $email = (string) ($user->email ?? '');
                $name = $email !== '' ? explode('@', $email)[0] : ('User #' . $user->id);
            }
            $userName = $name;
        }

        $row = CcMessageChecklist::create([
            'channel_id'                   => $channel->id,
            'channel'                      => $channel->channel,
            'user_id'                      => Auth::id(),
            'user_name'                    => $userName,
            'messages_resolved'            => (bool) ($validated['messages_resolved']            ?? false),
            'returns_resolved'             => (bool) ($validated['returns_resolved']             ?? false),
            'unresolved_messages_followup' => (bool) ($validated['unresolved_messages_followup'] ?? false),
            'activity_documented'          => (bool) ($validated['activity_documented']          ?? false),
            'notes'                        => $validated['notes'] ?? null,
            'submitted_at'                 => now(),
        ]);

        return response()->json([
            'success' => true,
            'row' => [
                'id'                            => $row->id,
                'channel_id'                    => $row->channel_id,
                'user_name'                     => $row->user_name,
                'submitted_at'                  => optional($row->submitted_at)->toIso8601String(),
                'messages_resolved'             => (bool) $row->messages_resolved,
                'returns_resolved'              => (bool) $row->returns_resolved,
                'unresolved_messages_followup'  => (bool) $row->unresolved_messages_followup,
                'activity_documented'           => (bool) $row->activity_documented,
            ],
        ]);
    }

    /**
     * Return the most recent checklist submissions for a single channel,
     * newest first. Used by the History modal on the CC Message & Returns
     * page.
     */
    public function getCcChecklistHistory(Request $request)
    {
        $validated = $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'limit'      => 'nullable|integer|min:1|max:200',
        ]);
        $limit = (int) ($validated['limit'] ?? 50);

        $rows = CcMessageChecklist::query()
            ->where('channel_id', (int) $validated['channel_id'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (CcMessageChecklist $r) => [
                'id'                            => $r->id,
                'user_name'                     => $r->user_name,
                'submitted_at'                  => optional($r->submitted_at)->toIso8601String(),
                'messages_resolved'             => (bool) $r->messages_resolved,
                'returns_resolved'              => (bool) $r->returns_resolved,
                'unresolved_messages_followup'  => (bool) $r->unresolved_messages_followup,
                'activity_documented'           => (bool) $r->activity_documented,
                'notes'                         => $r->notes,
            ])
            ->all();

        return response()->json(['rows' => $rows]);
    }

    /**
     * Persist a single submission of the per-channel Returns checklist —
     * powers the second Status + History column pair (placed after R link)
     * on the CC Message & Returns page. Same shape as storeCcChecklist()
     * but writes to cc_returns_checklists.
     */
    public function storeCcReturnsChecklist(Request $request)
    {
        $validated = $request->validate([
            'channel_id'                   => 'required|integer|exists:channel_master,id',
            'messages_resolved'            => 'sometimes|boolean',
            'unresolved_messages_followup' => 'sometimes|boolean',
            'activity_documented'          => 'sometimes|boolean',
            'notes'                        => 'nullable|string|max:2000',
        ]);

        $channel = ChannelMaster::query()->findOrFail((int) $validated['channel_id']);
        $user = Auth::user();
        $userName = null;
        if ($user) {
            $name = trim((string) ($user->name ?? ''));
            if ($name === '') {
                $email = (string) ($user->email ?? '');
                $name = $email !== '' ? explode('@', $email)[0] : ('User #' . $user->id);
            }
            $userName = $name;
        }

        $row = CcReturnsChecklist::create([
            'channel_id'                   => $channel->id,
            'channel'                      => $channel->channel,
            'user_id'                      => Auth::id(),
            'user_name'                    => $userName,
            'messages_resolved'            => (bool) ($validated['messages_resolved']            ?? false),
            'unresolved_messages_followup' => (bool) ($validated['unresolved_messages_followup'] ?? false),
            'activity_documented'          => (bool) ($validated['activity_documented']          ?? false),
            'notes'                        => $validated['notes'] ?? null,
            'submitted_at'                 => now(),
        ]);

        return response()->json([
            'success' => true,
            'row' => [
                'id'                            => $row->id,
                'channel_id'                    => $row->channel_id,
                'user_name'                     => $row->user_name,
                'submitted_at'                  => optional($row->submitted_at)->toIso8601String(),
                'messages_resolved'             => (bool) $row->messages_resolved,
                'unresolved_messages_followup'  => (bool) $row->unresolved_messages_followup,
                'activity_documented'           => (bool) $row->activity_documented,
            ],
        ]);
    }

    /**
     * Returns the most recent Returns-checklist submissions for a single
     * channel, newest first. Used by the second History modal on the
     * CC Message & Returns page.
     */
    public function getCcReturnsChecklistHistory(Request $request)
    {
        $validated = $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'limit'      => 'nullable|integer|min:1|max:200',
        ]);
        $limit = (int) ($validated['limit'] ?? 50);

        $rows = CcReturnsChecklist::query()
            ->where('channel_id', (int) $validated['channel_id'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (CcReturnsChecklist $r) => [
                'id'                            => $r->id,
                'user_name'                     => $r->user_name,
                'submitted_at'                  => optional($r->submitted_at)->toIso8601String(),
                'messages_resolved'             => (bool) $r->messages_resolved,
                'unresolved_messages_followup'  => (bool) $r->unresolved_messages_followup,
                'activity_documented'           => (bool) $r->activity_documented,
                'notes'                         => $r->notes,
            ])
            ->all();

        return response()->json(['rows' => $rows]);
    }

    // =================================================================
    //  Shipping-page endpoints — mirror the cc_message_*  and cc_returns_*
    //  variants but write to / read from cc_shipping_*  tables instead.
    //  Same validation rules, same auth gating, same response shape so
    //  the blade's helper JS works unchanged.
    // =================================================================

    /**
     * Shipping-page daily submission windows. Each side of the page has
     * its own time gate (in America/New_York hours):
     *   - 9AM Clear   (Messages side):  09:00–10:00
     *   - 3 PM Clear  (Returns side):   15:00–16:00
     * Outside the relevant window the corresponding store endpoint
     * returns 422. Days where the window passed without a submission
     * render as red "missed" rows in that side's history modal.
     */
    private const SHIPPING_CLEAR_TZ                  = 'America/New_York';
    private const SHIPPING_CLEAR_WINDOW_FROM         = 9;    // 9AM Clear from
    private const SHIPPING_CLEAR_WINDOW_TO           = 10;   // 9AM Clear to (exclusive)
    private const SHIPPING_RETURNS_WINDOW_FROM       = 15;   // 3PM Clear from
    private const SHIPPING_RETURNS_WINDOW_TO         = 16;   // 3PM Clear to (exclusive)

    public function storeCcShippingChecklist(Request $request)
    {
        $nowEst = \Carbon\Carbon::now(self::SHIPPING_CLEAR_TZ);
        $h = (int) $nowEst->hour;
        if ($h < self::SHIPPING_CLEAR_WINDOW_FROM || $h >= self::SHIPPING_CLEAR_WINDOW_TO) {
            return response()->json([
                'success' => false,
                'message' => '9AM Clear can only be submitted between '
                    . sprintf('%02d:00', self::SHIPPING_CLEAR_WINDOW_FROM)
                    . ' and '
                    . sprintf('%02d:00', self::SHIPPING_CLEAR_WINDOW_TO)
                    . ' ' . self::SHIPPING_CLEAR_TZ
                    . '. It is currently ' . $nowEst->format('H:i') . ' there.',
                'window_open' => false,
            ], 422);
        }

        return $this->genericStoreChecklist($request, CcShippingChecklist::class, false, true, true);
    }

    public function getCcShippingChecklistHistory(Request $request)
    {
        return $this->shippingChecklistHistoryWithMissed(
            $request,
            CcShippingChecklist::class,
            self::SHIPPING_CLEAR_WINDOW_TO,
            true
        );
    }

    public function getCcShippingReturnsChecklistHistory(Request $request)
    {
        return $this->shippingChecklistHistoryWithMissed(
            $request,
            CcShippingReturnsChecklist::class,
            self::SHIPPING_RETURNS_WINDOW_TO,
            true
        );
    }

    /**
     * Shared body for the two Shipping-page history endpoints. Loads the
     * raw submissions for the given model, then synthesizes red "missed"
     * rows for every EST day in the range that had no submission. The
     * `$windowToHour` controls when "today" counts as missed (i.e. once
     * the window's upper bound has passed in EST).
     */
    private function shippingChecklistHistoryWithMissed(
        Request $request,
        string $modelClass,
        int $windowToHour,
        bool $includeExtraCheck2 = false
    ) {
        // Pull the raw submissions first (newest → oldest).
        $base = $this->genericChecklistHistory($request, $modelClass, false, true, $includeExtraCheck2);
        $payload = json_decode($base->getContent(), true) ?: [];
        $rows = $payload['rows'] ?? [];

        // Bucket existing submissions by their EST date — any submission
        // on that EST date counts as "covered" so we don't double-report
        // old/late submissions as missed.
        $coveredDates = [];
        foreach ($rows as $r) {
            if (empty($r['submitted_at'])) continue;
            try {
                $date = \Carbon\Carbon::parse($r['submitted_at'])
                    ->setTimezone(self::SHIPPING_CLEAR_TZ)
                    ->toDateString();
                $coveredDates[$date] = true;
            } catch (\Throwable $e) {
                // ignore unparseable rows
            }
        }

        // Walk every day from the EARLIEST submission (or 30 days ago
        // when there are no submissions yet) up to "today" in EST. Skip
        // today if the window hasn't fully passed yet — the user can
        // still submit.
        $oldestRow = end($rows) ?: null;
        $start = $oldestRow && !empty($oldestRow['submitted_at'])
            ? \Carbon\Carbon::parse($oldestRow['submitted_at'])->setTimezone(self::SHIPPING_CLEAR_TZ)->startOfDay()
            : \Carbon\Carbon::now(self::SHIPPING_CLEAR_TZ)->subDays(30)->startOfDay();

        $nowEst   = \Carbon\Carbon::now(self::SHIPPING_CLEAR_TZ);
        $todayEst = $nowEst->copy()->startOfDay();
        $windowPassedToday = $nowEst->hour >= $windowToHour;
        $end = $windowPassedToday ? $todayEst : $todayEst->copy()->subDay();

        $missed = [];
        $cursor = $start->copy();
        $safetyCap = 400; // ~13 months — guards against runaway loops
        $iter = 0;
        while ($cursor->lessThanOrEqualTo($end) && $iter < $safetyCap) {
            $iter++;
            $d = $cursor->toDateString();
            if (! isset($coveredDates[$d])) {
                $missed[] = [
                    'id'           => null,
                    'user_name'    => null,
                    // Anchor the synthetic row at the END of the missed
                    // window (in EST) so it sorts naturally between real
                    // submissions.
                    'submitted_at' => $cursor->copy()->setTime($windowToHour, 0, 0)->toIso8601String(),
                    'status'       => 'missed',
                    'notes'        => null,
                ];
            }
            $cursor->addDay();
        }

        $merged = array_merge($rows, $missed);
        usort($merged, function ($a, $b) {
            return strcmp(($b['submitted_at'] ?? ''), ($a['submitted_at'] ?? ''));
        });

        return response()->json(['rows' => $merged]);
    }

    public function storeCcShippingReturnsChecklist(Request $request)
    {
        $nowEst = \Carbon\Carbon::now(self::SHIPPING_CLEAR_TZ);
        $h = (int) $nowEst->hour;
        if ($h < self::SHIPPING_RETURNS_WINDOW_FROM || $h >= self::SHIPPING_RETURNS_WINDOW_TO) {
            return response()->json([
                'success' => false,
                'message' => '3 PM Clear can only be submitted between '
                    . sprintf('%02d:00', self::SHIPPING_RETURNS_WINDOW_FROM)
                    . ' and '
                    . sprintf('%02d:00', self::SHIPPING_RETURNS_WINDOW_TO)
                    . ' ' . self::SHIPPING_CLEAR_TZ
                    . '. It is currently ' . $nowEst->format('H:i') . ' there.',
                'window_open' => false,
            ], 422);
        }

        return $this->genericStoreChecklist($request, CcShippingReturnsChecklist::class, false, true, true);
    }

    public function storeCcShippingNextValue(Request $request)
    {
        return $this->genericStoreNextValue($request, CcShippingChannelNext::class);
    }

    public function storeCcShippingReturnsNextValue(Request $request)
    {
        return $this->genericStoreNextValue($request, CcShippingReturnsChannelNext::class);
    }

    /**
     * Save the per-channel "S link" (Shipping link) for the Shipping page.
     * Open to everyone (matches the M/H/R-link behaviour on the
     * /customer-care/cc-messages-returns page — link editing isn't gated
     * to a manager).
     */
    public function storeCcShippingSLink(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'value'      => 'nullable|string|max:2048',
        ]);

        $channel = ChannelMaster::query()->findOrFail((int) $request->input('channel_id'));

        $raw  = $request->input('value');
        $link = $raw === null ? null : trim((string) $raw);
        if ($link === '') {
            $link = null;
        }

        $user = Auth::user();
        $userName = null;
        if ($user) {
            $name = trim((string) ($user->name ?? ''));
            if ($name === '') {
                $email = (string) ($user->email ?? '');
                $name = $email !== '' ? explode('@', $email)[0] : ('User #' . $user->id);
            }
            $userName = $name;
        }

        $row = CcShippingChannelLink::updateOrCreate(
            ['channel_id' => $channel->id],
            [
                's_link'             => $link,
                'updated_by_user_id' => Auth::id(),
                'updated_by_name'    => $userName,
            ]
        );

        return response()->json([
            'success'    => true,
            'channel_id' => $row->channel_id,
            'field'      => 's_link',
            'value'      => $row->s_link,
        ]);
    }

    /**
     * Shared body for "store one checklist submission" against any
     * cc_*_checklists model. Returns the same response shape used by
     * storeCcChecklist() so the existing blade JS can consume it unchanged.
     */
    private function genericStoreChecklist(
        Request $request,
        string $modelClass,
        bool $includeReturnsResolved,
        bool $includeExtraCheck = false,
        bool $includeExtraCheck2 = false
    ) {
        $rules = [
            'channel_id'                   => 'required|integer|exists:channel_master,id',
            'messages_resolved'            => 'sometimes|boolean',
            'unresolved_messages_followup' => 'sometimes|boolean',
            'activity_documented'          => 'sometimes|boolean',
            'notes'                        => 'nullable|string|max:2000',
        ];
        if ($includeReturnsResolved) {
            $rules['returns_resolved'] = 'sometimes|boolean';
        }
        if ($includeExtraCheck) {
            $rules['extra_check'] = 'sometimes|boolean';
        }
        if ($includeExtraCheck2) {
            $rules['extra_check_2'] = 'sometimes|boolean';
        }
        $validated = $request->validate($rules);

        $channel = ChannelMaster::query()->findOrFail((int) $validated['channel_id']);
        $user = Auth::user();
        $userName = null;
        if ($user) {
            $name = trim((string) ($user->name ?? ''));
            if ($name === '') {
                $email = (string) ($user->email ?? '');
                $name = $email !== '' ? explode('@', $email)[0] : ('User #' . $user->id);
            }
            $userName = $name;
        }

        $payload = [
            'channel_id'                   => $channel->id,
            'channel'                      => $channel->channel,
            'user_id'                      => Auth::id(),
            'user_name'                    => $userName,
            'messages_resolved'            => (bool) ($validated['messages_resolved']            ?? false),
            'unresolved_messages_followup' => (bool) ($validated['unresolved_messages_followup'] ?? false),
            'activity_documented'          => (bool) ($validated['activity_documented']          ?? false),
            'notes'                        => $validated['notes'] ?? null,
            'submitted_at'                 => now(),
        ];
        if ($includeReturnsResolved) {
            $payload['returns_resolved'] = (bool) ($validated['returns_resolved'] ?? false);
        }
        if ($includeExtraCheck) {
            $payload['extra_check'] = (bool) ($validated['extra_check'] ?? false);
        }
        if ($includeExtraCheck2) {
            $payload['extra_check_2'] = (bool) ($validated['extra_check_2'] ?? false);
        }

        $row = $modelClass::create($payload);

        $rowOut = [
            'id'                            => $row->id,
            'channel_id'                    => $row->channel_id,
            'user_name'                     => $row->user_name,
            'submitted_at'                  => optional($row->submitted_at)->toIso8601String(),
            'messages_resolved'             => (bool) $row->messages_resolved,
            'unresolved_messages_followup'  => (bool) $row->unresolved_messages_followup,
            'activity_documented'           => (bool) $row->activity_documented,
        ];
        if ($includeReturnsResolved) {
            $rowOut['returns_resolved'] = (bool) ($row->returns_resolved ?? false);
        }
        if ($includeExtraCheck) {
            $rowOut['extra_check'] = (bool) ($row->extra_check ?? false);
        }
        if ($includeExtraCheck2) {
            $rowOut['extra_check_2'] = (bool) ($row->extra_check_2 ?? false);
        }

        return response()->json(['success' => true, 'row' => $rowOut]);
    }

    /**
     * Shared body for "list history rows" against any cc_*_checklists
     * model. Same response shape as getCcChecklistHistory().
     */
    private function genericChecklistHistory(
        Request $request,
        string $modelClass,
        bool $includeReturnsResolved,
        bool $includeExtraCheck = false,
        bool $includeExtraCheck2 = false
    ) {
        $validated = $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'limit'      => 'nullable|integer|min:1|max:200',
        ]);
        $limit = (int) ($validated['limit'] ?? 50);

        $rows = $modelClass::query()
            ->where('channel_id', (int) $validated['channel_id'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function ($r) use ($includeReturnsResolved, $includeExtraCheck, $includeExtraCheck2) {
                $out = [
                    'id'                            => $r->id,
                    'user_name'                     => $r->user_name,
                    'submitted_at'                  => optional($r->submitted_at)->toIso8601String(),
                    'messages_resolved'             => (bool) $r->messages_resolved,
                    'unresolved_messages_followup'  => (bool) $r->unresolved_messages_followup,
                    'activity_documented'           => (bool) $r->activity_documented,
                    'notes'                         => $r->notes,
                ];
                if ($includeReturnsResolved) {
                    $out['returns_resolved'] = (bool) ($r->returns_resolved ?? false);
                }
                if ($includeExtraCheck) {
                    $out['extra_check'] = (bool) ($r->extra_check ?? false);
                }
                if ($includeExtraCheck2) {
                    $out['extra_check_2'] = (bool) ($r->extra_check_2 ?? false);
                }
                return $out;
            })
            ->all();

        return response()->json(['rows' => $rows]);
    }

    /**
     * Shared body for "save Next value" against any cc_*_channel_next
     * model. Same auth gate (NEXT_EDITOR_EMAILS) and response shape as
     * storeCcNextValue().
     */
    private function genericStoreNextValue(Request $request, string $modelClass)
    {
        if (! $this->canEditNextValue()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to edit this value.',
            ], 403);
        }

        $validated = $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'next_value' => 'nullable|integer|min:1|max:9',
        ]);

        $user = Auth::user();
        $userName = null;
        if ($user) {
            $name = trim((string) ($user->name ?? ''));
            if ($name === '') {
                $email = (string) ($user->email ?? '');
                $name = $email !== '' ? explode('@', $email)[0] : ('User #' . $user->id);
            }
            $userName = $name;
        }

        $row = $modelClass::updateOrCreate(
            ['channel_id' => (int) $validated['channel_id']],
            [
                'next_value'         => array_key_exists('next_value', $validated) ? $validated['next_value'] : null,
                'updated_by_user_id' => Auth::id(),
                'updated_by_name'    => $userName,
            ]
        );

        return response()->json([
            'success' => true,
            'row' => [
                'channel_id'      => $row->channel_id,
                'next_value'      => $row->next_value !== null ? (int) $row->next_value : null,
                'updated_by_name' => $row->updated_by_name,
                'updated_at'      => optional($row->updated_at)->toIso8601String(),
            ],
        ]);
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

        // For each channel, also pull the grade + auditor remarks from the most
        // recent audit (latest row by id — auto-increment matches insertion order).
        // We also pull auditor_id + created_at so the table can show who ran
        // the audit and at what exact time (the audit_date column is date-only).
        $latestAuditMap = AuditResult::where('module', 'cc_messages')
            ->whereIn('id', function ($q) {
                $q->from('audit_results')
                    ->selectRaw('MAX(id)')
                    ->where('module', 'cc_messages')
                    ->groupBy('channel');
            })
            ->get([
                'id', 'channel', 'grade',
                'auditor_notes', 'critical_failure_reasons',
                'auditor_id', 'audit_date', 'created_at',
            ])
            ->keyBy('channel');

        // Resolve auditor user_id -> display name (single query, no N+1).
        // Falls back to email local-part, then to "Auditor #<id>" when the
        // user row is missing.
        $auditorIds = $latestAuditMap
            ->pluck('auditor_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $auditorNameMap = [];
        if (!empty($auditorIds)) {
            $auditorNameMap = DB::table('users')
                ->whereIn('id', $auditorIds)
                ->get(['id', 'name', 'email'])
                ->mapWithKeys(function ($u) {
                    $name = trim((string) ($u->name ?? ''));
                    if ($name === '') {
                        $email = (string) ($u->email ?? '');
                        $name = $email !== '' ? explode('@', $email)[0] : ('User #' . $u->id);
                    }
                    return [$u->id => $name];
                })
                ->toArray();
        }

        // Per-parameter remarks from those latest audits — auditors usually leave
        // feedback against individual parameters, not the top-level "Notes" field,
        // so we pull them as structured items the front-end can render in a modal.
        $latestAuditIds = $latestAuditMap->pluck('id')->all();
        $itemsByAudit   = [];
        if (!empty($latestAuditIds)) {
            $items = AuditResultItem::whereIn('audit_result_id', $latestAuditIds)
                ->whereNotNull('remarks')
                ->where('remarks', '!=', '')
                ->orderBy('audit_result_id')
                ->orderBy('id')
                ->get(['audit_result_id', 'parameter_label', 'category', 'is_critical_failed', 'score', 'max_score', 'remarks']);
            foreach ($items as $it) {
                $text = trim((string) $it->remarks);
                if ($text === '') continue;
                $itemsByAudit[$it->audit_result_id][] = [
                    'label'        => $it->parameter_label,
                    'category'     => $it->category,
                    'score'        => (float) $it->score,
                    'max_score'    => (float) $it->max_score,
                    'critical'     => (bool) $it->is_critical_failed,
                    'remarks'      => $text,
                ];
            }
        }

        // Grade colours (module-scoped, with global fallback) keyed by grade letter
        $gradeColorMap = AuditGrade::bandsForModule('cc_messages')
            ->pluck('color', 'grade')
            ->toArray();

        // Decorate channels with their last audit date + latest grade / colour / remarks
        $channelsWithLogo = array_map(
            function ($row) use ($lastAuditMap, $latestAuditMap, $gradeColorMap, $itemsByAudit, $auditorNameMap) {
                $row['last_audited_at'] = $lastAuditMap[$row['channel']] ?? null;
                $latest = $latestAuditMap[$row['channel']] ?? null;
                $grade  = $latest->grade ?? null;
                $row['last_grade']       = $grade;
                $row['last_grade_color'] = $grade ? ($gradeColorMap[$grade] ?? null) : null;

                // Auditor (who ran the audit) + the exact timestamp it was
                // saved. created_at carries the time of day; audit_date is
                // date-only and used as a soft fallback.
                $auditorId = $latest->auditor_id ?? null;
                $row['last_auditor_id']   = $auditorId;
                $row['last_auditor_name'] = ($auditorId && isset($auditorNameMap[$auditorId]))
                    ? $auditorNameMap[$auditorId]
                    : null;
                $row['last_audited_full'] = $latest && $latest->created_at
                    ? $latest->created_at->toIso8601String()
                    : ($latest && $latest->audit_date
                        ? $latest->audit_date->toIso8601String()
                        : null);

                // Structured remarks for the modal (per-parameter feedback) + a
                // free-form auditor notes / critical-reasons block when present.
                $items = $latest ? ($itemsByAudit[$latest->id] ?? []) : [];
                $row['last_remarks_items'] = $items;
                $row['last_auditor_notes'] = $latest->auditor_notes ?? null;
                $row['last_critical_reasons'] = $latest->critical_failure_reasons ?? null;
                $row['last_remarks_count'] = count($items)
                    + (!empty($latest->auditor_notes) ? 1 : 0)
                    + (!empty($latest->critical_failure_reasons) ? 1 : 0);

                return $row;
            },
            $channelsWithLogo
        );

        $isAuditAdmin = $this->isAuditAdmin();
        $isSopAdmin   = $this->isSopAdmin();
        $sopHtml      = $this->loadSopHtml('cc_messages');
        $sopKey       = 'cc_messages';

        return view('audit-master.cc-messages-audit', compact(
            'channels', 'channelsWithLogo', 'isAuditAdmin',
            'isSopAdmin', 'sopHtml', 'sopKey'
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

    /**
     * GET the saved SOP HTML for a given module key. Everyone with access
     * to the audit page can read it; only SOP admins can save it back via
     * the companion saveSop() endpoint.
     *
     * GET /audit-master/sop?key=cc_messages
     */
    public function getSop(Request $request): JsonResponse
    {
        $key = (string) $request->query('key', '');
        if (! in_array($key, self::SOP_KEYS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown SOP key.',
            ], 422);
        }

        return response()->json([
            'success'   => true,
            'key'       => $key,
            'html'      => $this->loadSopHtml($key),
            'can_edit'  => $this->isSopAdmin(),
        ]);
    }

    /**
     * POST the SOP HTML for a given module key. Only the SOP_ADMIN_EMAILS
     * users can save (paste-in content from ChatGPT / external editor).
     *
     * POST /audit-master/sop
     *   key: cc_messages | cc_return | cc_replacement | cc_shipping
     *   html: raw HTML string (max 5 MB)
     */
    public function saveSop(Request $request): JsonResponse
    {
        if (! $this->isSopAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to edit the SOP.',
            ], 403);
        }

        $validated = $request->validate([
            'key'  => 'required|string|max:64',
            // ~5 MB cap; pasted SOPs from ChatGPT are typically a few hundred KB.
            'html' => 'nullable|string|max:5242880',
        ]);

        $key = $validated['key'];
        if (! in_array($key, self::SOP_KEYS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown SOP key.',
            ], 422);
        }

        $html = (string) ($validated['html'] ?? '');

        try {
            Storage::disk('local')->put($this->sopStoragePath($key), $html);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save SOP: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'key'     => $key,
            'bytes'   => strlen($html),
            'message' => 'SOP saved successfully.',
        ]);
    }
}
