<?php

namespace App\Http\Controllers;

use App\Models\FacebookAllAdsSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Backs the "Facebook All Ads Sheet" page (/facebook-all-ads-sheet).
 *
 * The page is a generic sheet importer: users upload any CSV / Excel / TSV
 * file and the rows are stored verbatim as JSON in `facebook_all_ads_sheet`.
 * The Tabulator on the front-end then renders whichever columns the upload
 * carried, without any schema change.
 */
class FacebookAllAdsSheetController extends Controller
{
    /**
     * Allowed ad-type values for each page-type filter. The first two keys
     * (`video` / `carousal`) are the legacy combined buckets that back the
     * standalone `/facebook-video-ads-sheet` + `/facebook-carousal-ads-sheet`
     * pages. The four single-type keys back the per-ad_type child pages
     * under `/facebook-ads/*` and `/instagram-ads/*`.
     */
    private const TYPE_FILTERS = [
        'video'           => ['GROUP VIDEO',    'PARENT VIDEO'],
        'carousal'        => ['GROUP CAROUSAL', 'PARENT CAROUSAL'],
        'group-video'     => ['GROUP VIDEO'],
        'group-carousal'  => ['GROUP CAROUSAL'],
        'parent-video'    => ['PARENT VIDEO'],
        'parent-carousal' => ['PARENT CAROUSAL'],
    ];

    /** All-ads page (no ad_type filter, full dropdown). */
    public function index()
    {
        return view('facebook-all-ads-sheet', [
            'pageType'       => 'all',
            'pageTitle'      => 'Meta Ads All',
            'pageSubtitle'   => 'Generic CSV / Excel / TSV importer — upload any sheet and view it as a table',
            'allowedAdTypes' => FacebookAllAdsSheet::AD_TYPES,
            'chOptions'      => FacebookAllAdsSheet::CH_OPTIONS,
        ]);
    }

    /** Video-only page — shows GROUP VIDEO + PARENT VIDEO rows. */
    public function videoIndex()
    {
        return view('facebook-all-ads-sheet', [
            'pageType'       => 'video',
            'pageTitle'      => 'Facebook Video Ads Sheet',
            'pageSubtitle'   => 'Rows tagged GROUP VIDEO or PARENT VIDEO',
            'allowedAdTypes' => self::TYPE_FILTERS['video'],
            'chOptions'      => FacebookAllAdsSheet::CH_OPTIONS,
        ]);
    }

    /** Facebook channel page — same sheet, lensed to CH = "FB" rows. */
    public function facebookIndex()
    {
        return view('facebook-all-ads-sheet', [
            'pageType'       => 'all',
            'pageTitle'      => 'Facebook',
            'pageSubtitle'   => 'Campaigns tagged CH = FB',
            'allowedAdTypes' => FacebookAllAdsSheet::AD_TYPES,
            'chOptions'      => FacebookAllAdsSheet::CH_OPTIONS,
            'chFilter'       => 'FB',
        ]);
    }

    /**
     * Render a child page lensed to a single (CH, ad_type) pair. Backs the eight
     * `/facebook-ads/*` and `/instagram-ads/*` single-type children. The shared
     * blade only needs `pageType` + `chFilter` + an `allowedAdTypes` whitelist.
     */
    private function renderTypedChannelChild(string $chFilter, string $typeKey, string $shortLabel): \Illuminate\View\View
    {
        $allowed = self::TYPE_FILTERS[$typeKey] ?? FacebookAllAdsSheet::AD_TYPES;
        $channelLabel = $chFilter === 'FB' ? 'Facebook' : 'Instagram';

        return view('facebook-all-ads-sheet', [
            'pageType'       => $typeKey,
            'pageTitle'      => $channelLabel.' — '.$shortLabel,
            'pageSubtitle'   => 'CH = '.$chFilter.' · '.implode(' / ', $allowed).' rows',
            'allowedAdTypes' => $allowed,
            'chOptions'      => FacebookAllAdsSheet::CH_OPTIONS,
            'chFilter'       => $chFilter,
        ]);
    }

    /** Facebook → G VIDEO child page: CH = "FB" + ad_type = "GROUP VIDEO". */
    public function facebookGroupVideoIndex(): \Illuminate\View\View
    {
        return $this->renderTypedChannelChild('FB', 'group-video', 'G Video');
    }

    /** Facebook → G CAROUSAL child page: CH = "FB" + ad_type = "GROUP CAROUSAL". */
    public function facebookGroupCarousalIndex(): \Illuminate\View\View
    {
        return $this->renderTypedChannelChild('FB', 'group-carousal', 'G Carousal');
    }

    /** Facebook → P VIDEO child page: CH = "FB" + ad_type = "PARENT VIDEO". */
    public function facebookParentVideoIndex(): \Illuminate\View\View
    {
        return $this->renderTypedChannelChild('FB', 'parent-video', 'P Video');
    }

    /** Facebook → P CAROUSAL child page: CH = "FB" + ad_type = "PARENT CAROUSAL". */
    public function facebookParentCarousalIndex(): \Illuminate\View\View
    {
        return $this->renderTypedChannelChild('FB', 'parent-carousal', 'P Carousal');
    }

    /** Instagram channel page — same sheet, lensed to CH = "Insta" rows. */
    public function instagramIndex()
    {
        return view('facebook-all-ads-sheet', [
            'pageType'       => 'all',
            'pageTitle'      => 'Instagram',
            'pageSubtitle'   => 'Campaigns tagged CH = Insta',
            'allowedAdTypes' => FacebookAllAdsSheet::AD_TYPES,
            'chOptions'      => FacebookAllAdsSheet::CH_OPTIONS,
            'chFilter'       => 'Insta',
        ]);
    }

    /** Instagram → G VIDEO child page: CH = "Insta" + ad_type = "GROUP VIDEO". */
    public function instagramGroupVideoIndex(): \Illuminate\View\View
    {
        return $this->renderTypedChannelChild('Insta', 'group-video', 'G Video');
    }

    /** Instagram → G CAROUSAL child page: CH = "Insta" + ad_type = "GROUP CAROUSAL". */
    public function instagramGroupCarousalIndex(): \Illuminate\View\View
    {
        return $this->renderTypedChannelChild('Insta', 'group-carousal', 'G Carousal');
    }

    /** Instagram → P VIDEO child page: CH = "Insta" + ad_type = "PARENT VIDEO". */
    public function instagramParentVideoIndex(): \Illuminate\View\View
    {
        return $this->renderTypedChannelChild('Insta', 'parent-video', 'P Video');
    }

    /** Instagram → P CAROUSAL child page: CH = "Insta" + ad_type = "PARENT CAROUSAL". */
    public function instagramParentCarousalIndex(): \Illuminate\View\View
    {
        return $this->renderTypedChannelChild('Insta', 'parent-carousal', 'P Carousal');
    }

    /** Carousal-only page — shows GROUP CAROUSAL + PARENT CAROUSAL rows. */
    public function carousalIndex()
    {
        return view('facebook-all-ads-sheet', [
            'pageType'       => 'carousal',
            'pageTitle'      => 'Facebook Carousal Ads Sheet',
            'pageSubtitle'   => 'Rows tagged GROUP CAROUSAL or PARENT CAROUSAL',
            'allowedAdTypes' => self::TYPE_FILTERS['carousal'],
            'chOptions'      => FacebookAllAdsSheet::CH_OPTIONS,
        ]);
    }

    /**
     * Tabulator data feed. Returns `{columns, data}` where columns are derived
     * from the union of keys present in the latest batch (or the requested
     * batch_id), and data is the row payloads.
     */
    public function getData(Request $request)
    {
        $batchId  = $request->query('batch_id');
        $view     = $request->query('view');     // 'merged' | null
        $typeKey  = $request->query('type');     // 'video' | 'carousal' | null
        $typeList = self::TYPE_FILTERS[$typeKey] ?? null;
        // Channel lens — 'FB' | 'Insta' (Facebook / Instagram pages).
        $chFilter = $request->query('ch');
        if (! in_array($chFilter, FacebookAllAdsSheet::CH_OPTIONS, true)) {
            $chFilter = null;
        }

        // Merged view: join the most recent Campaign batch with the most
        // recent Spend batch by `Campaign ID`. Default when no specific batch
        // is asked for, so the user sees one unified table out of the box.
        if ($batchId === null && ($view === 'merged' || $view === null)) {
            $merged = $this->getMergedView($typeList, $chFilter);
            if ($merged !== null) {
                return response()->json($merged);
            }
            // No data yet — fall through to the empty-payload below.
        }

        $query = FacebookAllAdsSheet::query()->orderBy('row_index');

        if ($typeList) {
            $query->whereIn('ad_type', $typeList);
        }

        if ($chFilter) {
            $query->where('ch', $chFilter);
        }

        if ($batchId) {
            $query->where('import_batch_id', $batchId);
        } else {
            // No batches at all — return an empty payload.
            return response()->json([
                'success' => true,
                'columns' => [],
                'data'    => [],
                'batch'   => null,
            ]);
        }

        $rows = $query->get(['id', 'row_index', 'row_data', 'ad_type', 'ch', 'source_filename', 'created_at']);

        // Build column list from the union of keys observed in this batch.
        // Skip any `__*` meta keys (e.g. `__upload_type`) — those are
        // controller-internal and shouldn't render as a column.
        $columns = [];
        foreach ($rows as $r) {
            foreach (array_keys($r->row_data ?? []) as $k) {
                if (str_starts_with($k, '__')) continue;
                if (! isset($columns[$k])) {
                    $columns[$k] = true;
                }
            }
        }
        $columnList = array_map(fn($k) => [
            'title' => $k,
            'field' => $k,
        ], array_keys($columns));

        // Flatten row_data, but hoist `__upload_type` to a top-level
        // `_upload_type` (matches the convention used by `_id`, `_row_index`)
        // and strip every `__*` key from the visible payload.
        $data = $rows->map(function ($r) {
            $rd          = $r->row_data ?? [];
            $uploadType  = $rd['__upload_type'] ?? null;
            $cleanedData = array_filter(
                $rd,
                fn($_, $k) => ! str_starts_with($k, '__'),
                ARRAY_FILTER_USE_BOTH
            );
            return array_merge(
                [
                    '_id'          => $r->id,
                    '_row_index'   => $r->row_index,
                    '_upload_type' => $uploadType,
                    'ad_type'      => $r->ad_type,
                    'ch'           => $r->ch,
                ],
                $cleanedData
            );
        });

        $meta = FacebookAllAdsSheet::query()
            ->where('import_batch_id', $batchId)
            ->select('source_filename', DB::raw('MIN(created_at) as uploaded_at'), DB::raw('COUNT(*) as row_count'))
            ->groupBy('source_filename')
            ->first();

        // Pluck the batch's upload_type from the first row's row_data.
        $batchUploadType = $rows->first()->row_data['__upload_type'] ?? null;

        return response()->json([
            'success' => true,
            'columns' => $columnList,
            'data'    => $data,
            'batch'   => [
                'id'              => $batchId,
                'source_filename' => $meta->source_filename ?? null,
                'upload_type'     => $batchUploadType,
                'uploaded_at'     => $meta->uploaded_at ?? null,
                'row_count'       => $meta ? (int) $meta->row_count : 0,
            ],
        ]);
    }

    /**
     * Build a merged row set: latest Campaign upload joined with latest
     * Spend upload by `Campaign ID`. Returns an associative array shaped
     * like {success, columns, data, batch} ready to JSON-encode, or null
     * when there are no batches yet.
     *
     * Type filtering (`$typeList` from /facebook-{video|carousal}-… pages)
     * is applied to the merged set just like it is to individual batches.
     */
    private function getMergedView(?array $typeList, ?string $chFilter = null): ?array
    {
        $latestByType = $this->latestBatchPerType();
        if (empty($latestByType)) {
            return null;
        }

        $batchIds = array_values($latestByType);
        $rowsQ = FacebookAllAdsSheet::query()
            ->whereIn('import_batch_id', $batchIds)
            ->orderBy('id');
        if ($typeList) {
            $rowsQ->whereIn('ad_type', $typeList);
        }
        $rows = $rowsQ->get(['id', 'row_index', 'row_data', 'ad_type', 'ch', 'import_batch_id']);

        // Build a `lowercase Campaign name → Campaign ID` lookup from the
        // Campaign batch. Used as a fallback for Spend / Sales rows whose
        // sheets don't carry a Campaign ID column — they still join cleanly
        // as long as the Campaign name matches a known campaign.
        $nameToCid = $this->buildNameToCidLookup($latestByType['campaign'] ?? null);

        // We do an INNER JOIN: only show campaigns that appear in BOTH
        // the latest Campaign sheet AND the latest Spend sheet. Campaigns
        // present in only one of the two are dropped from the merged view
        // (they're still visible by selecting their batch directly).
        $merged          = [];   // campaign_id => merged row payload
        $presence        = [];   // campaign_id => set of upload_types it appears in
        $rowsWithoutCid  = 0;    // diagnostic: rows skipped for missing both id + name match

        foreach ($rows as $r) {
            $rd          = $r->row_data ?? [];
            $uploadType  = $rd['__upload_type'] ?? null;
            $cleanedData = array_filter(
                $rd,
                fn($_, $k) => ! str_starts_with($k, '__'),
                ARRAY_FILTER_USE_BOTH
            );
            $cid = $this->findCampaignId($cleanedData);

            // Fallback: if the row has no Campaign ID but does have a
            // Campaign name that matches a row in the Campaign batch, use
            // that batch's id as the merge key.
            if (($cid === null || $cid === '') && $nameToCid) {
                $name = $cleanedData['Campaign name'] ?? null;
                if (is_string($name) && trim($name) !== '') {
                    $cid = $nameToCid[mb_strtolower(trim($name))] ?? null;
                }
            }

            if ($cid === null || $cid === '') {
                $rowsWithoutCid++;
                continue; // can't match without either id or known name
            }

            $presence[$cid][$uploadType ?? '_unknown'] = true;

            if (! isset($merged[$cid])) {
                $merged[$cid] = [
                    '_id'          => $r->id,
                    '_row_index'   => $r->row_index,
                    '_upload_type' => $uploadType,
                    '_campaign_id' => $cid,
                    'ad_type'      => $r->ad_type,
                    'ch'           => $r->ch,
                ];
            }

            // Don't let later rows overwrite a previously-filled cell with
            // an empty value (so blanks in Spend don't wipe Campaign data).
            foreach ($cleanedData as $k => $v) {
                $existing       = $merged[$cid][$k] ?? null;
                $emptyExisting  = $existing === null || $existing === '';
                $emptyIncoming  = $v === null || (is_string($v) && trim($v) === '');
                if ($emptyExisting && ! $emptyIncoming) {
                    $merged[$cid][$k] = $v;
                }
            }

            // Prefer the campaign-batch row as the persistence target for
            // ad_type edits, since the Campaign list is the canonical row.
            if ($uploadType === 'campaign') {
                $merged[$cid]['_id']     = $r->id;
                $merged[$cid]['ad_type'] = $r->ad_type;
                $merged[$cid]['ch']      = $r->ch;
            }
        }

        // LEFT JOIN with the Campaign batch as the canonical base.
        // We want the user to always see EVERY campaign from their Campaign
        // list (e.g. all 326 rows) with Spend/Sales metrics filled in where
        // matched, instead of dropping rows just because a metric is
        // missing. Falls back to Spend, then Sales, when Campaign hasn't
        // been uploaded yet — whichever type exists becomes the base.
        $availableTypes = array_keys($latestByType);
        $baseType = null;
        foreach (['campaign', 'spend', 'sales'] as $t) {
            if (isset($latestByType[$t])) { $baseType = $t; break; }
        }

        $unmatchedFromOtherSheets = 0; // diag: cids that exist in spend/sales but not in the base
        if ($baseType !== null) {
            foreach ($merged as $cid => $_) {
                if (empty($presence[$cid][$baseType])) {
                    unset($merged[$cid]);
                    $unmatchedFromOtherSheets++;
                }
            }
        }
        $unmatched = $unmatchedFromOtherSheets;

        // Project each merged row to the curated MERGED_COLUMNS set in two
        // passes: pass 1 resolves columns with `sources`, pass 2 computes
        // columns with `formula = [num, den, decimals]` using the values
        // resolved in pass 1.
        $projected = [];
        foreach ($merged as $cid => $row) {
            $clean = [
                '_id'          => $row['_id'],
                '_row_index'   => $row['_row_index'],
                '_upload_type' => $row['_upload_type'],
                '_campaign_id' => $row['_campaign_id'],
                'ad_type'      => $row['ad_type'],
                'ch'           => $row['ch'] ?? null,
            ];
            // Pass 1 — sources
            foreach (self::MERGED_COLUMNS as $col) {
                if (! isset($col['sources'])) continue;
                $value = null;
                foreach ($col['sources'] as $src) {
                    if (array_key_exists($src, $row) && $row[$src] !== null && $row[$src] !== '') {
                        $value = $row[$src];
                        break;
                    }
                }
                $clean[$col['title']] = $value;
            }
            // Pass 2 — formula columns (need pass-1 values)
            foreach (self::MERGED_COLUMNS as $col) {
                if (! isset($col['formula'])) continue;
                $numCol   = $col['formula'][0];
                $denCol   = $col['formula'][1];
                $decimals = $col['formula'][2] ?? 2;
                $mode     = $col['formula'][3] ?? null;
                $num      = $clean[$numCol] ?? null;
                $den      = $clean[$denCol] ?? null;
                if ($mode === 'acos') {
                    $clean[$col['title']] = $this->acosPct($num, $den, $decimals);
                } elseif ($mode === 'cps') {
                    $clean[$col['title']] = $this->cpsValue($num, $den, $decimals);
                } else {
                    $clean[$col['title']] = $this->divPct($num, $den, $decimals);
                }
            }
            // Pass 2b — `rule` columns. Run before formatters so the rule
            // sees raw numeric SPEND / SALES rather than "$1,234" strings.
            foreach (self::MERGED_COLUMNS as $col) {
                if (! isset($col['rule'])) continue;
                if ($col['rule'] === 'acos_budget') {
                    $match = $this->acosBudgetMatch(
                        $clean['SPEND'] ?? null,
                        $clean['SALES'] ?? null
                    );
                    $clean[$col['title']] = $match['sbgt'];
                    // Hidden field — the JS Sbgt/Acos formatter paints
                    // the cell background with this colour so users
                    // can scan the recommendation strength at a glance.
                    $clean['_sbgt_color'] = $match['color'];
                }
            }
            // Pass 3 — apply `formatter` to source-based columns.
            foreach (self::MERGED_COLUMNS as $col) {
                if (empty($col['formatter'])) continue;
                $clean[$col['title']] = $this->applyFormatter(
                    $clean[$col['title']] ?? null,
                    $col['formatter']
                );
            }
            $projected[] = $clean;
        }

        // Pass 4 — attach the latest audit for each campaign id so the
        // Audit / History columns can render from $row._audit_*. One
        // grouped query keeps this O(1) regardless of row count.
        $cids = collect($projected)
            ->pluck('CAMPAIGN ID')
            ->filter(fn($v) => is_string($v) && $v !== '')
            ->unique()
            ->values()
            ->all();
        $latestAudits = $this->latestAuditsFor($cids);
        foreach ($projected as &$row) {
            $cid = $row['CAMPAIGN ID'] ?? '';
            $a   = $latestAudits[$cid] ?? null;
            $row['_audit_score']    = $a['score_pct']      ?? null;
            $row['_audit_at']       = $a['audited_at']     ?? null;
            $row['_audit_by']       = $a['audited_by_name'] ?? null;
            $row['_audit_comments'] = $a['comments']       ?? null;
        }
        unset($row);

        // Pass 5 — write today's per-campaign metric snapshot so the
        // badge trend chart has a real history to query later. Same
        // pass runs every time the merged view is computed (every
        // page load, every upload), with `updateOrInsert` keyed on
        // (campaign_id, snapshot_date) so we always store the most
        // recent value of the day. Cheap because $projected is a
        // small in-memory array and the unique key catches dupes.
        try {
            $this->snapshotMergedView($projected);
        } catch (\Throwable $e) {
            // Snapshot writes must never break the page render —
            // log and continue.
            \Log::warning('FAAS snapshot failed: ' . $e->getMessage());
        }

        // Channel lens — keep only campaigns tagged with the requested CH
        // (FB / Insta). Applied after the snapshot pass so the daily
        // history still records every campaign regardless of the lens.
        if ($chFilter) {
            $projected = array_values(array_filter(
                $projected,
                fn($r) => ($r['ch'] ?? null) === $chFilter
            ));
        }

        $columnList = array_map(
            fn($c) => ['title' => $c['title'], 'field' => $c['title']],
            self::MERGED_COLUMNS
        );

        $joinLabel = $baseType
            ? ucfirst($baseType) . ' (base) ← ' . implode(' + ', array_map(
                'ucfirst',
                array_values(array_diff($availableTypes, [$baseType]))
            ))
            : implode(' + ', array_map('ucfirst', $availableTypes));
        return [
            'success' => true,
            'columns' => $columnList,
            'data'    => $projected,
            'batch'   => [
                'id'              => '__merged__',
                'source_filename' => "Merged · {$joinLabel}",
                'upload_type'     => 'merged',
                'uploaded_at'     => null,
                'row_count'       => count($projected),
                'unmatched'       => $unmatched, // cids in spend/sales NOT in the base
                'rows_without_id' => $rowsWithoutCid,
                'sources'         => $latestByType,
                'base_type'       => $baseType,
            ],
        ];
    }

    /**
     * Build a `Campaign ID → ad_type` map for new uploads to inherit from.
     *
     * We treat Ad Type as a **campaign-level** attribute (see
     * {@see updateAdType()}, which propagates every change to all rows with
     * the same Campaign ID). That means we can safely walk only the rows
     * that have a non-null `ad_type` here: if the user cleared a tag, every
     * row with that Campaign ID had its `ad_type` set to NULL, so nothing
     * resurrects from older batches.
     *
     * @return array<string, string>
     */
    private function buildAdTypeCarryMap(): array
    {
        $rows = FacebookAllAdsSheet::query()
            ->whereNotNull('ad_type')
            ->where('ad_type', '!=', '')
            ->orderByDesc('id')   // newest tag wins if any cid still has multiple
            ->get(['ad_type', 'row_data']);

        $map = [];
        foreach ($rows as $r) {
            $rd  = $r->row_data ?? [];
            $cid = $this->findCampaignId(array_filter(
                $rd,
                fn($_, $k) => ! str_starts_with($k, '__'),
                ARRAY_FILTER_USE_BOTH
            ));
            if ($cid !== null && $cid !== '' && ! isset($map[$cid])) {
                $map[$cid] = $r->ad_type;
            }
        }
        return $map;
    }

    /**
     * Build a `Campaign ID → ch` map so new uploads inherit the channel
     * (FB / Insta) a campaign was previously tagged with. Mirrors
     * {@see buildAdTypeCarryMap()} — CH is a campaign-level attribute too.
     *
     * @return array<string, string>
     */
    private function buildChCarryMap(): array
    {
        $rows = FacebookAllAdsSheet::query()
            ->whereNotNull('ch')
            ->where('ch', '!=', '')
            ->orderByDesc('id')
            ->get(['ch', 'row_data']);

        $map = [];
        foreach ($rows as $r) {
            $rd  = $r->row_data ?? [];
            $cid = $this->findCampaignId(array_filter(
                $rd,
                fn($_, $k) => ! str_starts_with($k, '__'),
                ARRAY_FILTER_USE_BOTH
            ));
            if ($cid !== null && $cid !== '' && ! isset($map[$cid])) {
                $map[$cid] = $r->ch;
            }
        }
        return $map;
    }

    /**
     * (numerator / denominator) * 100, formatted as "X.YY%" with `$decimals`
     * fractional digits. Returns null when either side is null/empty/non-
     * numeric, or when the denominator parses to zero.
     */
    private function divPct($num, $den, int $decimals = 2): ?string
    {
        $n = $this->parseNumeric($num);
        $d = $this->parseNumeric($den);
        if ($n === null || $d === null || $d == 0.0) return null;
        return number_format(($n / $d) * 100, $decimals) . '%';
    }

    /**
     * ACOS-specific percentage with the user-defined edge-cases that
     * `divPct()` deliberately doesn't apply (CTR / CVR want plain
     * "blank when denominator is zero" semantics):
     *
     *   • Both blank/null     → null  (no source data — leave blank)
     *   • spend == 0          → "0%"  (campaign hasn't spent — best-case)
     *   • spend > 0, sales==0 → "100%"(spent without selling — worst-case)
     *   • otherwise           → (spend / sales) * 100, formatted
     */
    private function acosPct($spend, $sales, int $decimals = 0): ?string
    {
        $s = $this->parseNumeric($spend);
        $r = $this->parseNumeric($sales);
        // No source data on either side → leave the cell blank.
        if ($s === null && $r === null) return null;
        // Spend = 0 (or missing & sales present) → 0 %.
        if ($s === null || $s == 0.0) {
            return number_format(0, $decimals) . '%';
        }
        // Spend > 0 and sales is 0/blank → 100 %.
        if ($r === null || $r == 0.0) {
            return number_format(100, $decimals) . '%';
        }
        return number_format(($s / $r) * 100, $decimals) . '%';
    }

    /**
     * Cost per sold = spend / units sold, rendered with a "$" prefix.
     * Unlike `divPct()` this does NOT multiply by 100 and does NOT
     * append "%" — the result is a dollar amount.
     *
     * Edge cases:
     *   • Both blank/null            → null  (no source data)
     *   • Spend = 0                  → "$0.0" (free clicks/impressions)
     *   • Spend > 0 but sold = 0     → null  (cannot compute "per sale")
     *   • Otherwise                  → "$X.Y" with `$decimals` fraction digits
     */
    private function cpsValue($spend, $sold, int $decimals = 1): ?string
    {
        $s = $this->parseNumeric($spend);
        $u = $this->parseNumeric($sold);
        if ($s === null && $u === null) return null;
        if ($s === null || $s == 0.0) {
            return '$' . number_format(0, $decimals);
        }
        if ($u === null || $u == 0.0) {
            // Spend with no sales — leave blank rather than rendering
            // a misleading huge / infinite figure.
            return null;
        }
        return '$' . number_format($s / $u, $decimals);
    }

    /**
     * Suggested-budget rule keyed off the ACOS bracket. Bands are loaded
     * from the `facebook_sbgt_rules` table (key = "facebook_all") so
     * users can edit them at runtime via the "Sbgt Rule" modal — same
     * pattern as /ebay/campaign-ads → ebay_sbid_rules.
     *
     * Bands are evaluated in ascending `acos_max` order, first match
     * wins. Set `acos_max = 9999` for the catch-all final band.
     *
     * Edge cases:
     *   • Both spend and sales blank/zero → null (no row data, leave empty).
     *   • Spend > 0 but sales == 0       → ACOS effectively infinite, so
     *                                       fall through to the catch-all
     *                                       band (the user's "> 50 %" row).
     */
    private function acosBudgetRule($spend, $sales): ?int
    {
        return $this->acosBudgetMatch($spend, $sales)['sbgt'];
    }

    /**
     * Resolve an ACOS bracket → both the suggested budget AND the
     * band's colour, so callers can paint cells / chips with the
     * matched band's colour.
     *
     * @return array{sbgt: ?int, color: ?string}
     */
    private function acosBudgetMatch($spend, $sales): array
    {
        $s = $this->parseNumeric($spend);
        $r = $this->parseNumeric($sales);
        if (($s === null || $s == 0.0) && ($r === null || $r == 0.0)) {
            return ['sbgt' => null, 'color' => null];
        }
        // Effective infinity — keep arithmetic finite so the catch-all
        // band still wins via the normal "<=" comparison.
        $acos = ($r === null || $r == 0.0)
            ? 99999.0
            : ($s / $r) * 100;

        $bands = $this->loadSbgtBands();
        foreach ($bands as $band) {
            $max = (float) ($band['acos_max'] ?? 9999);
            if ($acos <= $max) {
                return [
                    'sbgt'  => (int) ($band['sbgt'] ?? 0),
                    'color' => $band['color'] ?? null,
                ];
            }
        }
        $last = end($bands) ?: [];
        return [
            'sbgt'  => (int) ($last['sbgt'] ?? 1),
            'color' => $last['color'] ?? null,
        ];
    }

    /**
     * Load the stored Sbgt bands, sorted ascending by `acos_max`.
     * Falls back to {@see defaultSbgtRule()} when the table row is
     * missing — keeps the page usable even if the migration hasn't run.
     *
     * @return array<int, array{acos_max:float, sbgt:int, label?:string, color?:string}>
     */
    private function loadSbgtBands(): array
    {
        $row = DB::table('facebook_sbgt_rules')->where('key', 'facebook_all')->first();
        $rule = $row
            ? (json_decode($row->rule, true) ?: [])
            : $this->defaultSbgtRule();
        $bands = $rule['bands'] ?? $this->defaultSbgtRule()['bands'];
        usort($bands, fn($a, $b) => ((float) ($a['acos_max'] ?? 0)) <=> ((float) ($b['acos_max'] ?? 0)));
        return $bands;
    }

    /**
     * Hard-coded fallback rule = the brackets the user originally
     * requested. Also used by the migration to seed the default row.
     */
    private function defaultSbgtRule(): array
    {
        return [
            'bands' => [
                ['acos_max' => 10,   'sbgt' => 20, 'label' => 'Excellent', 'color' => '#ec4899'], // Magenta
                ['acos_max' => 20,   'sbgt' => 15, 'label' => 'Good',      'color' => '#22c55e'], // Green
                ['acos_max' => 30,   'sbgt' => 10, 'label' => 'Fair',      'color' => '#3b82f6'], // Blue
                ['acos_max' => 40,   'sbgt' => 5,  'label' => 'Poor',      'color' => '#ca8a04'], // Mustard
                ['acos_max' => 50,   'sbgt' => 2,  'label' => 'Bad',       'color' => '#f97316'], // Orange
                ['acos_max' => 9999, 'sbgt' => 1,  'label' => 'Critical',  'color' => '#dc2626'], // Red
            ],
        ];
    }

    /**
     * Upsert today's per-campaign metric snapshot for every row in
     * the projected merged view. One row per (campaign_id, today)
     * — re-running the same day overwrites the previous snapshot,
     * so the value reflects the latest uploads / Audit / Sbgt rule.
     *
     * @param array<int, array<string, mixed>> $rows  projected merged-view rows
     */
    private function snapshotMergedView(array $rows): void
    {
        if (empty($rows)) return;

        $today = now()->toDateString();
        $payloads = [];
        foreach ($rows as $r) {
            $cid = (string) ($r['CAMPAIGN ID'] ?? '');
            // Sanity: Meta campaign ids are long numeric strings; skip
            // anything else so we don't pollute the snapshot table.
            if ($cid === '' || ! preg_match('/^\d{6,}$/', $cid)) continue;
            $payloads[] = [
                'campaign_id'   => $cid,
                'snapshot_date' => $today,
                'impr'  => $this->parseNumeric($r['IMPR']  ?? null) ?? 0,
                'clk'   => $this->parseNumeric($r['CLK']   ?? null) ?? 0,
                'spend' => $this->parseNumeric($r['SPEND'] ?? null) ?? 0,
                'sales' => $this->parseNumeric($r['SALES'] ?? null) ?? 0,
                'sold'  => $this->parseNumeric($r['SOLD']  ?? null) ?? 0,
                'sbgt'  => $this->parseNumeric($r['Sbgt']  ?? null) ?? 0,
            ];
        }
        if (empty($payloads)) return;

        $now = now();
        foreach ($payloads as $p) {
            DB::table('facebook_campaign_metric_snapshots')->updateOrInsert(
                ['campaign_id' => $p['campaign_id'], 'snapshot_date' => $p['snapshot_date']],
                array_merge($p, ['updated_at' => $now, 'created_at' => $now])
            );
        }
    }

    /**
     * Bulk-fetch the latest audit for a list of Meta campaign ids.
     * One window-function query keeps the projection cost flat even
     * with thousands of campaigns.
     *
     * @param array<int, string> $cids
     * @return array<string, array{score_pct:int, audited_at:string, audited_by_name:?string, comments:?string}>
     */
    private function latestAuditsFor(array $cids): array
    {
        if (empty($cids)) return [];
        // Subquery picks the row with the largest id per campaign_id.
        // Using id (auto-increment) is monotonic with audited_at and
        // dodges any tie-breaker ambiguity if two audits land in the
        // same second.
        $sub = DB::table('facebook_campaign_audits')
            ->select('campaign_id', DB::raw('MAX(id) AS max_id'))
            ->whereIn('campaign_id', $cids)
            ->groupBy('campaign_id');

        $rows = DB::table('facebook_campaign_audits AS a')
            ->joinSub($sub, 'l', function ($j) {
                $j->on('a.id', '=', 'l.max_id');
            })
            ->select('a.campaign_id', 'a.score_pct', 'a.audited_at', 'a.audited_by_name', 'a.comments')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[$r->campaign_id] = [
                'score_pct'        => (int) $r->score_pct,
                'audited_at'       => (string) $r->audited_at,
                'audited_by_name'  => $r->audited_by_name,
                'comments'         => $r->comments,
            ];
        }
        return $map;
    }

    /**
     * GET endpoint — returns the audit checklist + the latest audit
     * + history for one campaign. The blade calls this when the user
     * clicks the Audit cell.
     *
     *   GET /facebook-all-ads-sheet/audit?campaign_id=120247…
     */
    public function getAudit(Request $request)
    {
        $cid = trim((string) $request->input('campaign_id', ''));
        if ($cid === '' || !preg_match('/^\d{6,}$/', $cid)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid or missing campaign_id.',
            ], 422);
        }

        $latest = DB::table('facebook_campaign_audits')
            ->where('campaign_id', $cid)
            ->orderByDesc('id')
            ->first();

        $history = DB::table('facebook_campaign_audits')
            ->where('campaign_id', $cid)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'score_pct', 'audited_at', 'audited_by_name', 'comments']);

        return response()->json([
            'success'   => true,
            'checklist' => self::AUDIT_CHECKLIST,
            'latest'    => $latest ? [
                'score_pct'       => (int) $latest->score_pct,
                'checks'          => json_decode($latest->checks, true) ?: new \stdClass(),
                'notes'           => (isset($latest->notes) ? json_decode($latest->notes, true) : null) ?: new \stdClass(),
                'custom_items'    => (isset($latest->custom_items) ? json_decode($latest->custom_items, true) : null) ?: [],
                'comments'        => $latest->comments,
                'audited_at'      => $latest->audited_at,
                'audited_by_name' => $latest->audited_by_name,
            ] : null,
            'history'   => $history,
        ]);
    }

    /**
     * POST endpoint — saves a new audit for a campaign. Each save
     * appends a row (full history is preserved); the merged-view
     * projection just shows the most recent.
     */
    public function saveAudit(Request $request)
    {
        $cid          = trim((string) $request->input('campaign_id', ''));
        $campaignName = (string) $request->input('campaign_name', '');
        $checks       = $request->input('checks', []);
        $notes        = $request->input('notes', []);
        $customItems  = $request->input('custom_items', []);
        $comments     = (string) $request->input('comments', '');

        if ($cid === '' || !preg_match('/^\d{6,}$/', $cid)) {
            return response()->json(['success' => false, 'error' => 'Invalid campaign_id.'], 422);
        }
        if (!is_array($checks)) {
            return response()->json(['success' => false, 'error' => 'checks must be an object.'], 422);
        }
        if (!is_array($notes)) {
            $notes = [];
        }
        if (!is_array($customItems)) {
            $customItems = [];
        }

        // Sanitise the manually-added checks: each needs a label and a
        // non-negative integer weight; a stable key is generated when the
        // client didn't supply one so notes/checks can map back to it.
        $cleanCustom = [];
        foreach ($customItems as $i => $ci) {
            if (!is_array($ci)) {
                continue;
            }
            $label = trim((string) ($ci['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $key = trim((string) ($ci['key'] ?? ''));
            if ($key === '') {
                $key = 'custom_' . ($i + 1);
            }
            $cleanCustom[] = [
                'key'     => $key,
                'label'   => mb_substr($label, 0, 255),
                'weight'  => max(0, (int) ($ci['weight'] ?? 0)),
                'checked' => ! empty($ci['checked']),
                'note'    => mb_substr(trim((string) ($ci['note'] ?? '')), 0, 1000),
            ];
        }

        // Score = sum(weights of items with key set to true) / sum(all weights) × 100.
        // Both the built-in checklist and any manually-added custom checks count.
        $totalWeight = 0;
        $earned      = 0;
        foreach (self::AUDIT_CHECKLIST as $item) {
            $totalWeight += (int) $item['weight'];
            if (! empty($checks[$item['key']])) {
                $earned += (int) $item['weight'];
            }
        }
        foreach ($cleanCustom as $item) {
            $totalWeight += (int) $item['weight'];
            if ($item['checked']) {
                $earned += (int) $item['weight'];
            }
        }
        $score = ($totalWeight > 0)
            ? (int) round(($earned / $totalWeight) * 100)
            : 0;

        $user = $request->user();
        $id   = DB::table('facebook_campaign_audits')->insertGetId([
            'campaign_id'      => $cid,
            'campaign_name'    => $campaignName !== '' ? $campaignName : null,
            'checks'           => json_encode($checks),
            'notes'            => json_encode((object) $notes),
            'custom_items'     => json_encode($cleanCustom),
            'score_pct'        => $score,
            'comments'         => $comments !== '' ? $comments : null,
            'audited_by'       => $user->id ?? null,
            'audited_by_name'  => $user->name ?? null,
            'audited_at'       => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return response()->json([
            'success'   => true,
            'id'        => $id,
            'score_pct' => $score,
            'audited_at' => now()->toDateTimeString(),
            'audited_by_name' => $user->name ?? null,
        ]);
    }

    /** GET endpoint — returns the current rule (with defaults if absent). */
    public function getRule()
    {
        $row = DB::table('facebook_sbgt_rules')->where('key', 'facebook_all')->first();
        return response()->json($row
            ? (json_decode($row->rule, true) ?: $this->defaultSbgtRule())
            : $this->defaultSbgtRule());
    }

    /**
     * POST endpoint — replaces the rule with the supplied bands.
     * Bands are normalised (numeric coercion + sort) before persisting
     * so the projection pass never has to defensively re-sort.
     */
    public function saveRule(Request $request)
    {
        $bands = $request->input('bands', []);
        if (! is_array($bands) || count($bands) === 0) {
            return response()->json(['success' => false, 'error' => 'At least one band is required.'], 422);
        }

        $clean = [];
        foreach ($bands as $b) {
            $clean[] = [
                'acos_max' => (float) ($b['acos_max'] ?? 9999),
                'sbgt'     => (int)   ($b['sbgt']     ?? 0),
                'label'    => (string)($b['label']    ?? ''),
                'color'    => (string)($b['color']    ?? '#6c757d'),
            ];
        }
        usort($clean, fn($a, $b) => $a['acos_max'] <=> $b['acos_max']);

        DB::table('facebook_sbgt_rules')->updateOrInsert(
            ['key' => 'facebook_all'],
            ['rule' => json_encode(['bands' => $clean]), 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['success' => true, 'rule' => ['bands' => $clean]]);
    }

    /**
     * Push per-campaign Sbgt values as the new `daily_budget` on Meta.
     *
     * Frontend sends `{ campaigns: [{ campaign_id, sbgt }, ...] }`.
     * For each entry we POST to:
     *
     *     https://graph.facebook.com/{v}/{campaign_id}
     *     ?access_token=…
     *     &daily_budget=<sbgt-in-account-currency-minor-units>
     *
     * Meta expects the budget in the account currency's minor unit
     * (cents for USD), so we multiply by 100. Each call is logged to
     * `meta_action_logs` for an audit trail. Campaigns whose budget
     * lives at the ad-set level will be reported as "failed" with the
     * Meta error message — the user can then either move CBO to the
     * campaign level or push to ad sets directly.
     *
     * Same intent as EbayCampaignAdsController::pushSbid() but inline
     * (no artisan command), because the source-of-truth Sbgt comes
     * from the user's uploaded sheets, not a stored value.
     */
    public function pushSbgt(Request $request)
    {
        $campaigns = $request->input('campaigns', []);
        if (! is_array($campaigns) || count($campaigns) === 0) {
            return response()->json([
                'success' => false,
                'error'   => 'Nothing to push — supply campaigns: [{campaign_id, sbgt}].',
            ], 422);
        }

        // Each campaign is one Meta API call plus a short backoff — a batch
        // of ~95 GROUP VIDEO rows can exceed the default 30–60 s PHP limit.
        $count = count($campaigns);
        set_time_limit(max(120, $count * 4));
        ignore_user_abort(true);

        $accessToken = config('services.meta.access_token');
        $apiVersion  = config('services.meta.api_version', 'v21.0');
        if (! $accessToken) {
            return response()->json([
                'success' => false,
                'error'   => 'META_ACCESS_TOKEN not configured.',
            ], 500);
        }

        $userId  = optional($request->user())->id;
        $base    = "https://graph.facebook.com/{$apiVersion}";
        $pushed  = 0;
        $failed  = 0;
        $skipped = 0;
        $results = [];

        foreach ($campaigns as $row) {
            $cid  = isset($row['campaign_id']) ? trim((string) $row['campaign_id']) : '';
            $sbgt = isset($row['sbgt']) ? $row['sbgt'] : null;
            $sbgtNum = $this->parseNumeric($sbgt);

            // Skip rows with no campaign id or no recommendation — these
            // are the "blank Sbgt" rows the user could see in the table.
            if ($cid === '' || ! preg_match('/^\d{6,}$/', $cid)) {
                $skipped++;
                $results[] = [
                    'campaign_id' => $cid,
                    'status'      => 'skipped',
                    'reason'      => 'Missing or invalid campaign id',
                ];
                continue;
            }
            if ($sbgtNum === null || $sbgtNum <= 0) {
                $skipped++;
                $results[] = [
                    'campaign_id' => $cid,
                    'status'      => 'skipped',
                    'reason'      => 'No Sbgt value to push',
                ];
                continue;
            }

            // Meta expects minor-unit integers (cents for USD).
            $minorUnits = (int) round($sbgtNum * 100);

            try {
                $resp = \Illuminate\Support\Facades\Http::asForm()
                    ->timeout(20)
                    ->post("{$base}/{$cid}", [
                        'access_token' => $accessToken,
                        'daily_budget' => $minorUnits,
                    ]);

                $body = $resp->json() ?? [];
                $ok   = $resp->successful() && (($body['success'] ?? false) === true || isset($body['id']));

                DB::table('meta_action_logs')->insert([
                    'user_id'            => $userId ?? 0,
                    'action_type'        => 'update_budget',
                    'entity_type'        => 'campaign',
                    'entity_meta_id'     => $cid,
                    'status'             => $ok ? 'success' : 'failed',
                    'request_payload'    => json_encode(['daily_budget' => $minorUnits, 'sbgt' => $sbgtNum]),
                    'response_payload'   => json_encode($body),
                    'error_message'      => $ok ? null : ($body['error']['message'] ?? null),
                    'meta_error_code'    => $ok ? null : (string) ($body['error']['code'] ?? ''),
                    'meta_error_message' => $ok ? null : ($body['error']['error_user_msg'] ?? $body['error']['message'] ?? null),
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                if ($ok) {
                    $pushed++;
                    $results[] = [
                        'campaign_id' => $cid,
                        'status'      => 'pushed',
                        'sbgt'        => $sbgtNum,
                    ];
                    // Mirror the new budget locally so subsequent reads
                    // of meta_campaigns reflect what we just wrote.
                    DB::table('meta_campaigns')
                        ->where('meta_id', $cid)
                        ->update(['daily_budget' => $sbgtNum, 'updated_at' => now()]);
                } else {
                    $failed++;
                    $results[] = [
                        'campaign_id' => $cid,
                        'status'      => 'failed',
                        'reason'      => $body['error']['message'] ?? ('HTTP ' . $resp->status()),
                    ];
                }
            } catch (\Throwable $e) {
                $failed++;
                $results[] = [
                    'campaign_id' => $cid,
                    'status'      => 'failed',
                    'reason'      => $e->getMessage(),
                ];
                DB::table('meta_action_logs')->insert([
                    'user_id'        => $userId ?? 0,
                    'action_type'    => 'update_budget',
                    'entity_type'    => 'campaign',
                    'entity_meta_id' => $cid,
                    'status'         => 'failed',
                    'request_payload' => json_encode(['daily_budget' => $minorUnits, 'sbgt' => $sbgtNum]),
                    'error_message'  => $e->getMessage(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            // Small breather between calls so a long batch doesn't trip
            // Meta's rate limiter.
            usleep(150000); // 0.15 s
        }

        return response()->json([
            'success' => true,
            'pushed'  => $pushed,
            'failed'  => $failed,
            'skipped' => $skipped,
            'results' => $results,
        ]);
    }

    /**
     * Returns a daily history series for one of the toolbar badges,
     * sourced from `meta_insights_daily` (which already accumulates a
     * row-per-campaign-per-day from Meta's API).
     *
     * Query params:
     *   • metric        — one of impr|clk|spend|sales|sold|acos|ctr|cvr
     *   • days          — rolling window length, default 32
     *   • campaign_ids  — optional comma-separated Meta IDs; when
     *                     supplied, only those campaigns contribute to
     *                     each day's sum (lets the chart match the
     *                     subset of rows the user is currently looking
     *                     at on the page).
     *
     * Response shape (matches the chart code on /all-marketplace-master):
     *   { metric, days, data: [{ date: 'May 19', value: 12345 }, ...] }
     */
    public function badgeHistory(Request $request)
    {
        $metric = strtolower((string) $request->input('metric', ''));
        $days   = max(1, min(180, (int) $request->input('days', 32)));
        $cidsCsv = (string) $request->input('campaign_ids', '');
        $cids = array_values(array_filter(array_map('trim', explode(',', $cidsCsv)), fn($v) => $v !== ''));

        $allowed = ['impr', 'clk', 'spend', 'sales', 'sold', 'sbgt', 'acos', 'ctr', 'cvr'];
        if (! in_array($metric, $allowed, true)) {
            return response()->json([
                'success' => false,
                'error'   => "Unknown metric '$metric'. Valid: " . implode(',', $allowed),
            ], 422);
        }

        // Aggregate the per-campaign daily snapshot table by date so
        // the chart's daily values exactly match the rollup the user
        // sees on the badges.
        $base = DB::table('facebook_campaign_metric_snapshots')
            ->where('snapshot_date', '>=', now()->subDays($days)->toDateString());
        if (! empty($cids)) {
            $base->whereIn('campaign_id', $cids);
        }
        $rows = $base->groupBy('snapshot_date')
            ->orderBy('snapshot_date')
            ->selectRaw('snapshot_date,
                SUM(impr)  AS impr,
                SUM(clk)   AS clk,
                SUM(spend) AS spend,
                SUM(sales) AS sales,
                SUM(sold)  AS sold,
                SUM(sbgt)  AS sbgt')
            ->get();

        $data = [];
        foreach ($rows as $r) {
            $impr  = (float) $r->impr;
            $clk   = (float) $r->clk;
            $spend = (float) $r->spend;
            $sales = (float) $r->sales;
            $sold  = (float) $r->sold;
            $sbgt  = (float) $r->sbgt;

            switch ($metric) {
                case 'impr':  $value = $impr;             break;
                case 'clk':   $value = $clk;              break;
                case 'spend': $value = round($spend, 2);  break;
                case 'sales': $value = round($sales, 2);  break;
                case 'sold':  $value = $sold;             break;
                case 'sbgt':  $value = round($sbgt, 2);   break;
                case 'acos':
                    // Apply the same edge-case rules as the column.
                    if ($spend == 0.0)    { $value = 0; }
                    elseif ($sales == 0.0) { $value = 100; }
                    else                   { $value = round(($spend / $sales) * 100, 1); }
                    break;
                case 'ctr':   $value = $impr  > 0 ? round(($clk  / $impr)  * 100, 1) : 0; break;
                case 'cvr':   $value = $clk   > 0 ? round(($sold / $clk)   * 100, 1) : 0; break;
                default:      $value = 0;
            }

            $data[] = [
                'date'  => date('M d', strtotime($r->snapshot_date)),
                'value' => $value,
            ];
        }

        return response()->json([
            'success' => true,
            'metric'  => $metric,
            'days'    => $days,
            'data'    => $data,
        ]);
    }

    /**
     * Render a resolved-source value using the column's formatter:
     *   • 'int' — round to integer, preserving "$" prefix and "%" suffix
     *             when the original value carried them. Non-numeric inputs
     *             pass through unchanged.
     */
    private function applyFormatter($value, string $formatter): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        $n = $this->parseNumeric($value);
        if ($n === null) return (string) $value;

        switch ($formatter) {
            case 'int':
                $orig   = is_string($value) ? $value : (string) $value;
                $prefix = (strpos($orig, '$') !== false) ? '$' : '';
                $suffix = (strpos($orig, '%') !== false) ? '%' : '';
                return $prefix . number_format(round($n)) . $suffix;
            case 'usd_int':
                // Always prefixes "$" regardless of whether the source
                // value already contained one — used for Meta exports
                // that ship "Amount spent (USD)" as a bare number.
                return '$' . number_format(round($n));
            default:
                return (string) $value;
        }
    }

    /**
     * Parse a metric value into a float, tolerating Shopify-style decoration
     * ("$232.19", "1,000.92", "4.49%", em-dashes "—" / "-"). Returns null
     * when the input has no usable digits.
     */
    private function parseNumeric($v): ?float
    {
        if ($v === null) return null;
        if (is_int($v) || is_float($v)) return (float) $v;
        $s = trim((string) $v);
        if ($s === '' || $s === '—' || $s === '-' || $s === 'N/A') return null;
        $s = preg_replace('/[^\d.\-]/', '', $s) ?? '';
        if ($s === '' || $s === '-' || $s === '.') return null;
        return (float) $s;
    }

    /**
     * Build a `lowercase(trim(Campaign name)) → Campaign ID` lookup from
     * the rows of one batch (typically the Campaign batch). Used by the
     * merger as a fallback so sheets that omit Campaign ID can still be
     * joined when their Campaign name matches a known campaign.
     *
     * @return array<string, string>
     */
    private function buildNameToCidLookup(?string $batchId): array
    {
        if (! $batchId) return [];

        $rows = FacebookAllAdsSheet::query()
            ->where('import_batch_id', $batchId)
            ->get(['row_data']);

        $map = [];
        foreach ($rows as $r) {
            $rd = (array) ($r->row_data ?? []);
            $cleaned = array_filter(
                $rd,
                fn($_, $k) => ! str_starts_with($k, '__'),
                ARRAY_FILTER_USE_BOTH
            );
            $cid  = $this->findCampaignId($cleaned);
            $name = $cleaned['Campaign name'] ?? null;
            if ($cid && is_string($name) && trim($name) !== '') {
                $key = mb_strtolower(trim($name));
                if (! isset($map[$key])) {
                    $map[$key] = $cid;
                }
            }
        }
        return $map;
    }

    /**
     * Locate the Campaign ID column inside a row.
     *
     * Recognises three column-name conventions (all case-insensitive):
     *   • "Campaign ID" / "CAMPAIGN ID" / "campaign_id"   — Campaign list,
     *     Meta Spend, Meta Sales-objective exports.
     *   • "Campaign activities"                            — Shopify
     *     Attribution export. The cell value here is the Meta campaign id
     *     (e.g. "120247090510380496"). Falsy values like "(No name)" or
     *     "{{campaign_name}}" are skipped so they don't pollute the merge.
     */
    private function findCampaignId(array $rowData): ?string
    {
        foreach ($rowData as $key => $value) {
            $k = trim((string) $key);
            if (preg_match('/^campaign[\s_]?id$/i', $k)
                || preg_match('/^campaign\s+activities$/i', $k)) {
                $clean = trim((string) $value);
                if ($clean === '') continue;
                // Skip the obvious non-id markers Shopify emits.
                $low = mb_strtolower($clean);
                if ($low === '(no name)' || $low === '{{campaign_name}}') continue;
                // Only treat numeric ids as canonical (15+ digits is the
                // shape of a Meta campaign meta_id). Falls back to the raw
                // value if the user actually used names as ids.
                return $clean;
            }
        }
        return null;
    }

    /**
     * Walk recent batches and return ['campaign'=>batchId, 'spend'=>batchId]
     * — i.e. the most recent batch of each upload_type. Falls back to header
     * detection for legacy batches that pre-date the `__upload_type` tag.
     *
     * @return array<string, string>
     */
    private function latestBatchPerType(): array
    {
        $batches = FacebookAllAdsSheet::query()
            ->select('import_batch_id', DB::raw('MIN(id) as first_id'))
            ->groupBy('import_batch_id')
            ->orderByDesc(DB::raw('MIN(id)'))
            ->limit(50)
            ->get();

        if ($batches->isEmpty()) return [];

        $firstIds  = $batches->pluck('first_id')->all();
        $firstRows = FacebookAllAdsSheet::whereIn('id', $firstIds)
            ->pluck('row_data', 'id');

        $latestByType = [];
        foreach ($batches as $b) {
            $rd   = $firstRows[$b->first_id] ?? null;
            if (! is_array($rd)) continue;

            $type = $rd['__upload_type'] ?? null;
            if (! $type) {
                // Legacy batch — sniff from headers.
                $headers = array_keys(array_filter(
                    $rd,
                    fn($_, $k) => ! str_starts_with($k, '__'),
                    ARRAY_FILTER_USE_BOTH
                ));
                $type = $this->detectFormat($headers);
            }
            if ($type && in_array($type, self::UPLOAD_TYPES, true)
                && ! isset($latestByType[$type])) {
                $latestByType[$type] = $b->import_batch_id;
            }
        }
        return $latestByType;
    }

    /**
     * Persist the Ad Type chosen from the dropdown. Propagates the value
     * (or NULL when cleared) to **every** row in the table that shares the
     * same Campaign ID, so Ad Type behaves as a campaign-level attribute
     * rather than a single-row one. This way:
     *
     *   • Setting a tag survives any re-upload (Spend, Sales, or Campaign
     *     re-import) because new rows inherit via the carry-over map.
     *   • Clearing a tag also survives — there's no leftover row hiding the
     *     same value that could resurrect on the next upload.
     *
     * Accepts an empty string in `ad_type` to clear the value.
     */
    public function updateAdType(Request $request, int $id)
    {
        $request->validate([
            'ad_type' => ['nullable', 'string', 'in:' . implode(',', FacebookAllAdsSheet::AD_TYPES)],
        ]);

        $row = FacebookAllAdsSheet::findOrFail($id);
        $newAdType = $request->input('ad_type') ?: null;

        // Propagate to every row that shares this row's Campaign ID. We
        // identify the cid from the row's row_data (handles both
        // "Campaign ID" and Shopify's "Campaign activities").
        $cid = $this->findCampaignId(array_filter(
            (array) ($row->row_data ?? []),
            fn($_, $k) => ! str_starts_with($k, '__'),
            ARRAY_FILTER_USE_BOTH
        ));

        $propagated = 1;
        if ($cid !== null && $cid !== '') {
            // Two patterns to update on, since the cid can live under either
            // a `Campaign ID`-flavoured key or `Campaign activities`.
            $like1 = '%"' . str_replace('%', '\\%', $cid) . '"%';

            $propagated = FacebookAllAdsSheet::query()
                ->where(function ($q) use ($cid, $like1) {
                    // JSON contains-style lookup that works on MySQL >= 5.7
                    // without needing JSON functions (cid values are always
                    // numeric strings, no escaping headaches).
                    $q->where('row_data', 'like', $like1);
                })
                ->get(['id', 'row_data'])
                ->filter(function ($r) use ($cid) {
                    $rd = (array) ($r->row_data ?? []);
                    $rowCid = $this->findCampaignId(array_filter(
                        $rd,
                        fn($_, $k) => ! str_starts_with($k, '__'),
                        ARRAY_FILTER_USE_BOTH
                    ));
                    return $rowCid === $cid;
                })
                ->each(function ($r) use ($newAdType) {
                    $r->ad_type = $newAdType;
                    $r->save();
                })
                ->count();
        } else {
            // No cid — just update this single row.
            $row->ad_type = $newAdType;
            $row->save();
        }

        return response()->json([
            'success'    => true,
            'id'         => $row->id,
            'ad_type'    => $newAdType,
            'campaign_id'=> $cid,
            'propagated' => $propagated,
        ]);
    }

    /**
     * Persist the CH (channel) chosen from the dropdown. Propagates the
     * value (or NULL when cleared) to every row that shares the same
     * Campaign ID, so CH behaves as a campaign-level attribute — exactly
     * like {@see updateAdType()}. Accepts an empty string to clear.
     */
    public function updateCh(Request $request, int $id)
    {
        $request->validate([
            'ch' => ['nullable', 'string', 'in:' . implode(',', FacebookAllAdsSheet::CH_OPTIONS)],
        ]);

        $row   = FacebookAllAdsSheet::findOrFail($id);
        $newCh = $request->input('ch') ?: null;

        $cid = $this->findCampaignId(array_filter(
            (array) ($row->row_data ?? []),
            fn($_, $k) => ! str_starts_with($k, '__'),
            ARRAY_FILTER_USE_BOTH
        ));

        $propagated = 1;
        if ($cid !== null && $cid !== '') {
            $like1 = '%"' . str_replace('%', '\\%', $cid) . '"%';

            $propagated = FacebookAllAdsSheet::query()
                ->where('row_data', 'like', $like1)
                ->get(['id', 'row_data'])
                ->filter(function ($r) use ($cid) {
                    $rd = (array) ($r->row_data ?? []);
                    $rowCid = $this->findCampaignId(array_filter(
                        $rd,
                        fn($_, $k) => ! str_starts_with($k, '__'),
                        ARRAY_FILTER_USE_BOTH
                    ));
                    return $rowCid === $cid;
                })
                ->each(function ($r) use ($newCh) {
                    $r->ch = $newCh;
                    $r->save();
                })
                ->count();
        } else {
            $row->ch = $newCh;
            $row->save();
        }

        return response()->json([
            'success'    => true,
            'id'         => $row->id,
            'ch'         => $newCh,
            'campaign_id'=> $cid,
            'propagated' => $propagated,
        ]);
    }

    /**
     * Bulk-set the CH (channel) column for a list of campaign ids.
     *
     * Frontend sends `{ ch: 'FB'|'Insta'|'', campaign_ids: [...] }`.
     * For every supplied campaign id we update every row that shares it
     * (same per-campaign propagation as {@see updateCh()}). An empty `ch`
     * clears the value. Returns how many campaigns and rows were touched.
     */
    public function bulkCh(Request $request)
    {
        $request->validate([
            'ch'             => ['nullable', 'string', 'in:' . implode(',', FacebookAllAdsSheet::CH_OPTIONS)],
            'campaign_ids'   => ['required', 'array', 'min:1'],
            'campaign_ids.*' => ['string'],
        ]);

        $newCh = $request->input('ch') ?: null;
        $cids  = collect($request->input('campaign_ids', []))
            ->map(fn($c) => trim((string) $c))
            ->filter(fn($c) => $c !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($cids)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid campaign ids supplied.',
            ], 422);
        }

        $campaignsUpdated = 0;
        $rowsUpdated      = 0;

        DB::beginTransaction();
        try {
            foreach ($cids as $cid) {
                $like = '%"' . str_replace('%', '\\%', $cid) . '"%';

                $touched = FacebookAllAdsSheet::query()
                    ->where('row_data', 'like', $like)
                    ->get(['id', 'row_data'])
                    ->filter(function ($r) use ($cid) {
                        $rowCid = $this->findCampaignId(array_filter(
                            (array) ($r->row_data ?? []),
                            fn($_, $k) => ! str_starts_with($k, '__'),
                            ARRAY_FILTER_USE_BOTH
                        ));
                        return $rowCid === $cid;
                    })
                    ->each(function ($r) use ($newCh) {
                        $r->ch = $newCh;
                        $r->save();
                    })
                    ->count();

                if ($touched > 0) {
                    $campaignsUpdated++;
                    $rowsUpdated += $touched;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Bulk CH update failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success'           => true,
            'ch'                => $newCh,
            'campaigns_updated' => $campaignsUpdated,
            'rows_updated'      => $rowsUpdated,
        ]);
    }

    /**
     * List every distinct upload batch so the page can offer a switcher.
     */
    public function batches()
    {
        // Pull the aggregate row + a representative `row_data` (from the
        // batch's lowest-id row) so we can extract `__upload_type` without
        // adding a JSON_EXTRACT clause that wouldn't work on older MySQL.
        $batches = FacebookAllAdsSheet::query()
            ->select(
                'import_batch_id',
                DB::raw('MIN(source_filename) as source_filename'),
                DB::raw('MIN(id) as first_id'),
                DB::raw('MIN(created_at) as uploaded_at'),
                DB::raw('COUNT(*) as row_count')
            )
            ->groupBy('import_batch_id')
            ->orderByDesc(DB::raw('MIN(id)'))
            ->limit(50)
            ->get();

        // Resolve the upload_type for each batch from the first row's row_data.
        $firstIds   = $batches->pluck('first_id')->all();
        $firstRows  = FacebookAllAdsSheet::whereIn('id', $firstIds)->pluck('row_data', 'id');

        $batches = $batches->map(function ($b) use ($firstRows) {
            $rd = $firstRows[$b->first_id] ?? null;
            $b->upload_type = is_array($rd) ? ($rd['__upload_type'] ?? null) : null;
            unset($b->first_id);
            return $b;
        });

        return response()->json([
            'success' => true,
            'data'    => $batches,
        ]);
    }

    /**
     * Accept a sheet upload (CSV / TSV / TXT / XLSX / XLS / ODS / .ads / etc.)
     * Parse it, store one row per data row keyed by the first row's headers,
     * and return the new batch id so the page can refresh the table.
     */
    /** Allowed `upload_type` values used by the modal's dropdown. */
    private const UPLOAD_TYPES = ['campaign', 'spend', 'sales'];

    /**
     * The curated columns the Merged view exposes — fixed and friendly,
     * not the union of every uploaded sheet's columns. Each entry is the
     * display title + an ordered list of row_data keys to read from
     * (first non-empty wins). The synthetic `_campaign_id` key (set by
     * findCampaignId during the merge) is checked first for the ID column
     * so Shopify's "Campaign activities" column works automatically.
     */
    /**
     * Curated columns for the Merged view. Each column has either:
     *   • `sources`  — list of row_data keys to read (first non-empty wins).
     *   • `formula`  — [numerator_col, denominator_col, decimals=2] computed
     *                  as (num / den) * 100 and formatted "X.YY%".
     *   • `formatter`— how to render the resolved value:
     *                  'int' → round to whole number, preserving "$" prefix.
     */
    private const MERGED_COLUMNS = [
        ['title' => 'Campaign name', 'sources' => ['Campaign name']],
        ['title' => 'CAMPAIGN ID',   'sources' => ['_campaign_id', 'Campaign ID', 'CAMPAIGN ID', 'Campaign activities']],
        // External link to the campaign (from the Campaign sheet's
        // "Link" column). Rendered as a clickable URL by the JS
        // formatter when the value looks like an http(s) URL.
        ['title' => 'Link',          'sources' => ['Link', 'link', 'URL', 'Url', 'url']],
        // ACOS = SPEND / SALES * 100, rounded to integer.
        // Mode "acos" applies the user-defined edge-case rules:
        //   • spend == 0           → 0 %
        //   • spend > 0, sales == 0 → 100 %
        ['title' => 'Acos',          'formula' => ['SPEND', 'SALES', 0, 'acos']],
        ['title' => 'IMPR',          'sources' => ['Impressions']],
        ['title' => 'CLK',           'sources' => ['Clicks (all)']],
        // CTR = CLK / IMPR * 100, rounded to 1 decimal
        ['title' => 'CTR',           'formula' => ['CLK', 'IMPR', 1]],
        ['title' => 'SPEND',         'sources' => ['Amount spent (USD)'], 'formatter' => 'usd_int'],
        ['title' => 'SALES',         'sources' => ['Sales'],               'formatter' => 'int'],
        ['title' => 'SOLD',          'sources' => ['Orders']],
        // CVR = SOLD / CLK * 100, rounded to 1 decimal
        ['title' => 'CVR',           'formula' => ['SOLD', 'CLK', 1]],
        // CPS = SPEND / SOLD (cost per sold), rounded to whole dollars,
        // rendered with a "$" prefix. Mode "cps" skips the *100 and
        // the trailing "%" that `divPct()` adds.
        ['title' => 'CPS',           'formula' => ['SPEND', 'SOLD', 0, 'cps']],
        // Sbgt = suggested budget driven by the ACOS bracket — see
        // acosBudgetRule(). Placed at the end so the row reads as
        // "metrics → recommendation".
        ['title' => 'Sbgt',          'rule'    => 'acos_budget'],
        // Audit + History — placeholder columns. The cell value comes
        // from facebook_campaign_audits (latest row per campaign id);
        // the projection writes `_audit_score`, `_audit_at`,
        // `_audit_by`, `_audit_comments` onto each row and the JS
        // formatters render the Audit button + History summary from
        // those hidden fields.
        ['title' => 'Audit',         'sources' => []],
        ['title' => 'History',       'sources' => []],
        // Campaign delivery state, sourced verbatim from Meta's Spend
        // export (column header: "Campaign delivery"). Values include
        // "Active", "In draft", "Paused", "Inactive", etc.
        ['title' => 'Status',        'sources' => ['Campaign delivery']],
    ];

    /**
     * Audit checklist — intentionally empty. The Audit modal has no
     * built-in/static checks: every check is manually added by the user
     * via the "Add custom check" box and stored in custom_items. Old rows
     * keep whatever built-in keys they had at audit time, so history is
     * unaffected.
     */
    private const AUDIT_CHECKLIST = [];

    /**
     * Header-signature rules used by {@see validateUploadFormat()} to make
     * sure the file the user uploaded actually matches the type they picked
     * in the modal. Each rule lists case-insensitive substrings that must
     * appear in (or be absent from) the joined header row.
     */
    private const FORMAT_RULES = [
        'campaign' => [
            // Two-column "Campaign name / Campaign ID" sheet. ID column must
            // be present, and none of the metric columns may appear.
            'required_any' => ['campaign id', 'campaign_id'],
            'forbidden'    => ['sessions', 'spend', 'amount spent', 'impressions', 'reporting starts'],
            'description'  => 'a Campaign list (columns: "Campaign name" + "Campaign ID" + optional "Link")',
        ],
        'spend' => [
            // Any Meta Ads Manager export with spend metrics — covers both
            // the plain Spend report (Reach / Impressions / Amount spent /
            // Clicks) and the Sales-objective campaign export which adds
            // "Reporting starts", "Result indicator", "Shops-assisted
            // purchases" on top. Sessions (Shopify) is the only forbidden
            // column — that's what `sales` is for now.
            'required_any' => ['spend', 'amount spent', 'impressions'],
            'forbidden'    => ['sessions', 'campaign activities'],
            'description'  => 'a Meta Ads Manager export (Spend / Impressions / Clicks — plain Spend or Sales-objective)',
        ],
        'sales' => [
            // Shopify Marketing → Attribution → Campaign activities export.
            // The signature columns (`Campaign activities` + Sessions + Sales
            // + Orders) only show up together in this specific Shopify report.
            // Meta exports (which carry Reporting starts / Result indicator /
            // Shops-assisted purchases) are forbidden here — they belong to
            // the Spend type.
            'required_all' => [
                'campaign activities',
                'sessions',
                'sales',
                'orders',
            ],
            'forbidden'    => [
                'reporting starts',
                'result indicator',
                'shops-assisted purchases',
                'amount spent',
            ],
            'description'  => 'a Shopify Sales export (Campaign activities / Channel / Type / Sessions / Sales / Orders / Conversion / Cost / ROAS / CPA / CTR / AOV / Orders from / Orders from returning customers)',
        ],
    ];

    public function upload(Request $request)
    {
        $request->validate([
            // Allow ANY file type — we sniff the format ourselves.
            'file'        => 'required|file|max:51200',
            // Required so each batch is tagged with one of the three kinds
            // the user picked in the modal (saves UI space vs. 3 buttons).
            'upload_type' => ['required', 'string', 'in:' . implode(',', self::UPLOAD_TYPES)],
        ]);

        $file       = $request->file('file');
        $ext        = strtolower($file->getClientOriginalExtension());
        $name       = $file->getClientOriginalName();
        $uploadType = $request->input('upload_type');

        try {
            $rows = $this->parseFile($file->getRealPath(), $ext);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse file: ' . $e->getMessage(),
            ], 422);
        }

        if (count($rows) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'File contains no data rows after the header row.',
            ], 422);
        }

        // First non-empty row is treated as the header row.
        $headerRowIdx = 0;
        foreach ($rows as $i => $r) {
            if (count(array_filter($r, fn($v) => $v !== null && trim((string) $v) !== '')) > 0) {
                $headerRowIdx = $i;
                break;
            }
        }

        $rawHeaders = $rows[$headerRowIdx];
        $headers    = [];
        foreach ($rawHeaders as $idx => $h) {
            $clean = trim((string) $h);
            if ($clean === '') {
                $clean = "column_" . ($idx + 1);
            }
            // Disambiguate duplicate header names so JSON keys are unique.
            $base    = $clean;
            $counter = 2;
            while (in_array($clean, $headers, true)) {
                $clean = $base . '_' . $counter++;
            }
            $headers[$idx] = $clean;
        }

        // Sanity-check: does the file actually look like a {$uploadType}?
        // If not, refuse — the user almost certainly picked the wrong option.
        $formatErr = $this->validateUploadFormat($uploadType, $headers);
        if ($formatErr) {
            return response()->json([
                'success' => false,
                'message' => $formatErr,
            ], 422);
        }

        $batchId  = (string) Str::uuid();
        $userId   = auth()->id();
        $imported = 0;

        // Build a Campaign ID → ad_type carry-over map BEFORE inserting the
        // new rows, so re-uploading any sheet preserves Ad Type tags the
        // user previously set on rows with the same Campaign ID. Without
        // this, every new Campaign upload silently wipes those tags.
        $prevAdTypeByCid = $this->buildAdTypeCarryMap();
        $prevChByCid     = $this->buildChCarryMap();

        DB::beginTransaction();
        try {
            foreach (array_slice($rows, $headerRowIdx + 1) as $i => $row) {
                $assoc = [];
                foreach ($headers as $idx => $h) {
                    $val = $row[$idx] ?? null;
                    $assoc[$h] = is_string($val) ? trim($val) : $val;
                }
                // Drop wholly-empty rows.
                $hasValue = false;
                foreach ($assoc as $v) {
                    if ($v !== null && $v !== '' && $v !== false) { $hasValue = true; break; }
                }
                if (! $hasValue) continue;

                // Carry over Ad Type from a previous row with the same
                // Campaign ID, if one exists. (Lookup uses the cleaned $assoc
                // BEFORE we prepend the __upload_type meta key, so the column
                // detection works the same as on previously-stored rows.)
                $rowCid     = $this->findCampaignId($assoc);
                $carriedAdType = $rowCid !== null ? ($prevAdTypeByCid[$rowCid] ?? null) : null;
                $carriedCh     = $rowCid !== null ? ($prevChByCid[$rowCid] ?? null) : null;

                // Tuck the upload type inside the existing `row_data` JSON
                // (under a double-underscore meta key) so we don't need a new
                // DB column. The display layer hides keys starting with `__`.
                $assoc = ['__upload_type' => $uploadType] + $assoc;

                FacebookAllAdsSheet::create([
                    'import_batch_id' => $batchId,
                    'source_filename' => $name,
                    'row_index'       => $headerRowIdx + 2 + $i,
                    'row_data'        => $assoc,
                    'ad_type'         => $carriedAdType,
                    'ch'              => $carriedCh,
                    'uploaded_by'     => $userId,
                ]);
                $imported++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save rows: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success'     => true,
            'message'     => "Imported {$imported} row(s) from {$name}",
            'batch_id'    => $batchId,
            'filename'    => $name,
            'upload_type' => $uploadType,
            'imported'    => $imported,
            'columns'     => array_values($headers),
        ]);
    }

    /**
     * Verify the uploaded file's header row matches the upload type the user
     * picked from the modal dropdown. Returns null on success or a
     * human-friendly error string. The check is case-insensitive substring
     * matching against the joined header row, so small wording variations
     * ("Spend" vs "Amount spent (USD)") are tolerated.
     */
    private function validateUploadFormat(string $uploadType, array $headers): ?string
    {
        $rule = self::FORMAT_RULES[$uploadType] ?? null;
        if (! $rule) {
            return null; // unknown type — defensive, validation should have caught it
        }

        $joined = mb_strtolower(implode('|', $headers));

        // Forbidden columns — if present, the file is one of the *other* types.
        foreach ($rule['forbidden'] ?? [] as $bad) {
            if (mb_strpos($joined, mb_strtolower($bad)) !== false) {
                $detected = $this->detectFormat($headers);
                $hint     = $detected
                    ? "Did you mean to pick \"" . ucfirst($detected) . "\"?"
                    : 'Make sure you picked the right type from the dropdown.';
                return "This file doesn't look like {$rule['description']} — it has a \"{$bad}\" column which belongs to a different format. {$hint}";
            }
        }

        // required_all — every listed substring must appear somewhere.
        foreach ($rule['required_all'] ?? [] as $needle) {
            if (mb_strpos($joined, mb_strtolower($needle)) === false) {
                return "This file doesn't look like {$rule['description']} — expected a column containing \"{$needle}\".";
            }
        }

        // required_any — at least one of the listed substrings must appear.
        if (! empty($rule['required_any'])) {
            $hit = false;
            foreach ($rule['required_any'] as $needle) {
                if (mb_strpos($joined, mb_strtolower($needle)) !== false) {
                    $hit = true; break;
                }
            }
            if (! $hit) {
                $list = '"' . implode('", "', $rule['required_any']) . '"';
                return "This file doesn't look like {$rule['description']} — expected at least one column matching {$list}.";
            }
        }

        return null;
    }

    /**
     * Best-effort guess at which `upload_type` the headers actually represent
     * — used purely to make the rejection message helpful ("Did you mean…?").
     */
    private function detectFormat(array $headers): ?string
    {
        $joined = mb_strtolower(implode('|', $headers));

        // Shopify Attribution: campaign activities + sessions is the
        // unmistakable signature.
        if (mb_strpos($joined, 'campaign activities') !== false
            && mb_strpos($joined, 'sessions') !== false) {
            return 'sales';
        }
        // Plain Meta Spend / Meta Sales-objective both fall under Spend now.
        if (mb_strpos($joined, 'amount spent') !== false
            || mb_strpos($joined, 'impressions') !== false
            || preg_match('/(^|\|)spend(\||$)/', $joined)) {
            return 'spend';
        }
        if (mb_strpos($joined, 'campaign id') !== false
            || mb_strpos($joined, 'campaign_id') !== false) {
            return 'campaign';
        }
        return null;
    }

    /**
     * Read any tabular file into a 0-indexed array of rows. Handles:
     *
     *   • PhpSpreadsheet-supported spreadsheets (xlsx, xls, xlsm, ods)
     *   • Delimited text (csv, tsv, txt, .ads, anything else) with auto-
     *     detected delimiter (tab > comma > semicolon > pipe).
     *
     * @return array<int, array<int, mixed>>
     */
    private function parseFile(string $path, string $ext): array
    {
        $spreadsheetExts = ['xlsx', 'xls', 'xlsm', 'ods'];

        if (in_array($ext, $spreadsheetExts, true)) {
            $spreadsheet = IOFactory::load($path);
        } else {
            // CSV-family: sniff delimiter from the first non-empty line.
            $delim = $this->sniffDelimiter($path);
            $reader = new CsvReader();
            $reader->setDelimiter($delim);
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);
            $reader->setInputEncoding(CsvReader::GUESS_ENCODING);

            $spreadsheet = $reader->load($path);
        }

        return $this->normalizeRows($spreadsheet);
    }

    private function sniffDelimiter(string $path): string
    {
        $sample = '';
        $h = @fopen($path, 'r');
        if ($h) {
            // Read up to ~32k of content so we don't get fooled by a short
            // first line that lacks delimiters.
            $sample = (string) fread($h, 32768);
            fclose($h);
        }
        $candidates = ["\t" => 0, ',' => 0, ';' => 0, '|' => 0];
        foreach ($candidates as $d => &$c) {
            $c = substr_count($sample, $d);
        }
        unset($c);
        arsort($candidates);
        $top = array_key_first($candidates);
        return $candidates[$top] > 0 ? $top : ',';
    }

    /**
     * Convert a Spreadsheet to a clean 0-indexed array, trimming the trailing
     * fully-empty rows that PhpSpreadsheet sometimes appends.
     *
     * @return array<int, array<int, mixed>>
     */
    private function normalizeRows(Spreadsheet $spreadsheet): array
    {
        $sheet = $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, false);

        // Trim trailing empty rows (common with .xlsx files that store a
        // big "used range" beyond the actual data).
        while (! empty($rows)) {
            $last = end($rows);
            $allEmpty = true;
            foreach ($last as $cell) {
                if ($cell !== null && trim((string) $cell) !== '') {
                    $allEmpty = false; break;
                }
            }
            if ($allEmpty) array_pop($rows);
            else break;
        }
        return array_values($rows);
    }
}
