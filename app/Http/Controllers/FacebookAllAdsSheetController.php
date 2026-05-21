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
    /** Allowed ad-type values for each page-type filter. */
    private const TYPE_FILTERS = [
        'video'    => ['GROUP VIDEO',    'PARENT VIDEO'],
        'carousal' => ['GROUP CAROUSAL', 'PARENT CAROUSAL'],
    ];

    /** All-ads page (no ad_type filter, full dropdown). */
    public function index()
    {
        return view('facebook-all-ads-sheet', [
            'pageType'       => 'all',
            'pageTitle'      => 'Facebook All Ads Sheet',
            'pageSubtitle'   => 'Generic CSV / Excel / TSV importer — upload any sheet and view it as a table',
            'allowedAdTypes' => FacebookAllAdsSheet::AD_TYPES,
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
        ]);
    }

    /** Carousal-only page — shows GROUP CAROUSAL + PARENT CAROUSAL rows. */
    public function carousalIndex()
    {
        return view('facebook-all-ads-sheet', [
            'pageType'       => 'carousal',
            'pageTitle'      => 'Facebook Carousal Ads Sheet',
            'pageSubtitle'   => 'Rows tagged GROUP CAROUSAL or PARENT CAROUSAL',
            'allowedAdTypes' => self::TYPE_FILTERS['carousal'],
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

        // Merged view: join the most recent Campaign batch with the most
        // recent Spend batch by `Campaign ID`. Default when no specific batch
        // is asked for, so the user sees one unified table out of the box.
        if ($batchId === null && ($view === 'merged' || $view === null)) {
            $merged = $this->getMergedView($typeList);
            if ($merged !== null) {
                return response()->json($merged);
            }
            // No data yet — fall through to the empty-payload below.
        }

        $query = FacebookAllAdsSheet::query()->orderBy('row_index');

        if ($typeList) {
            $query->whereIn('ad_type', $typeList);
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

        $rows = $query->get(['id', 'row_index', 'row_data', 'ad_type', 'source_filename', 'created_at']);

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
    private function getMergedView(?array $typeList): ?array
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
        $rows = $rowsQ->get(['id', 'row_index', 'row_data', 'ad_type', 'import_batch_id']);

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

        // Project each merged row to the curated MERGED_COLUMNS set —
        // friendly uppercase headers, only the fields the user asked for,
        // and nothing else from the raw uploads.
        $projected = [];
        foreach ($merged as $cid => $row) {
            $clean = [
                '_id'          => $row['_id'],
                '_row_index'   => $row['_row_index'],
                '_upload_type' => $row['_upload_type'],
                '_campaign_id' => $row['_campaign_id'],
                'ad_type'      => $row['ad_type'],
            ];
            foreach (self::MERGED_COLUMNS as $col) {
                $value = null;
                foreach ($col['sources'] as $src) {
                    if (array_key_exists($src, $row) && $row[$src] !== null && $row[$src] !== '') {
                        $value = $row[$src];
                        break;
                    }
                }
                $clean[$col['title']] = $value;
            }
            $projected[] = $clean;
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
    private const MERGED_COLUMNS = [
        ['title' => 'Campaign name', 'sources' => ['Campaign name']],
        ['title' => 'CAMPAIGN ID',   'sources' => ['_campaign_id', 'Campaign ID', 'CAMPAIGN ID', 'Campaign activities']],
        ['title' => 'IMPRESSIONS',   'sources' => ['Impressions']],
        ['title' => 'CLICKS',        'sources' => ['Clicks (all)']],
        ['title' => 'SPEND',         'sources' => ['Amount spent (USD)']],
        ['title' => 'SALES',         'sources' => ['Sales']],
        ['title' => 'SOLD',          'sources' => ['Orders']],
    ];

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
            'description'  => 'a Campaign list (columns: "Campaign name" + "Campaign ID")',
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
