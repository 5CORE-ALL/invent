<?php

namespace App\Http\Controllers;

use App\Models\ChannelMaster;
use App\Models\VideoAdAudienceOption;
use App\Models\VideoAdsHookOption;
use App\Models\VideoAdsMaster;
use App\Models\VideoAdsMasterCheckHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VideoAdsMasterController extends Controller
{
    public function index()
    {
        return view('video-ads-master');
    }

    /**
     * Returns the full grid payload in one call:
     *   - rows              : video_ads_master records (with timestamps)
     *   - channels          : list of channels from channel_master (CHANNEL dropdown)
     *   - hook_options      : list of HOOK tags (from video_ads_hook_options,
     *                         seeded from any hooks already used on rows)
     *   - audience_options  : list of AUDIENCE tags (from video_ad_audience_options,
     *                         seeded from any audiences already used on rows)
     *
     * The SKU/PARENT/GROUP column is just a fixed type selector now (SKU,
     * Parent, Group), so no lookup list is needed for it.
     */
    public function getData()
    {
        $rows = VideoAdsMaster::orderByDesc('id')->get();

        $channels = ChannelMaster::query()
            ->whereNotNull('channel')
            ->where('channel', '!=', '')
            ->orderBy('channel')
            ->pluck('channel')
            ->filter()
            ->unique()
            ->values();

        // Pull any hooks / audiences already stored on rows into the option
        // tables so the dropdowns reflect existing data, then collapse
        // "Foo Hook" / "Foo" pairs into a single video-hook type.
        $this->syncHookOptionsFromRows($rows);
        $this->dedupeHookOptions();
        $this->syncAudienceOptionsFromRows($rows);

        // Re-load rows after hook renames so the grid shows cleaned tags.
        $rows = VideoAdsMaster::orderByDesc('id')->get();

        return response()->json([
            'success'          => true,
            'rows'             => $rows,
            'channels'         => $channels,
            'hook_options'     => $this->hookOptionsPayload(),
            'audience_options' => $this->audienceOptionsPayload(),
        ]);
    }

    /**
     * Create a new row from the Add form. target_type is the only required
     * field; everything else is free-form text.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'target_type'  => 'required|in:sku,parent,group',
            'target_value' => 'nullable|string|max:255',
            'name'         => 'nullable|string|max:255',
            'channel'      => 'nullable|string|max:255',
            'audience'     => 'nullable|string',
            'hook_name'    => 'nullable|string',
            'hook'         => 'nullable|string',
            'link'         => 'nullable|string',
        ]);

        $row = VideoAdsMaster::create($data);

        return response()->json(['success' => true, 'row' => $row]);
    }

    /**
     * Update an existing row from the Edit form. Accepts any subset of
     * fields (all are optional / nullable) and writes them through.
     */
    public function update(Request $request, $id)
    {
        $row = VideoAdsMaster::findOrFail($id);

        $data = $request->validate([
            'target_type'  => 'sometimes|nullable|in:sku,parent,group',
            'target_value' => 'sometimes|nullable|string|max:255',
            'name'         => 'sometimes|nullable|string|max:255',
            'channel'      => 'sometimes|nullable|string|max:255',
            'audience'     => 'sometimes|nullable|string',
            'hook_name'    => 'sometimes|nullable|string',
            'hook'         => 'sometimes|nullable|string',
            'link'         => 'sometimes|nullable|string',
        ]);

        $row->fill($data)->save();

        return response()->json(['success' => true, 'row' => $row]);
    }

    public function destroy($id)
    {
        $row = VideoAdsMaster::findOrFail($id);
        $row->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Duplicate an existing row. We use Eloquent's replicate() so every
     * fillable attribute is carried over (target_type, name, channel, audience,
     * hook_name, hook, link). The new row gets a fresh id + timestamps.
     */
    public function copy($id)
    {
        $original = VideoAdsMaster::findOrFail($id);

        $copy = $original->replicate();
        // A duplicated row starts life unchecked — the check state and its
        // audit fields are per-row and should not be carried over.
        $copy->is_checked    = false;
        $copy->checked_by    = null;
        $copy->checked_at    = null;
        $copy->ad_checked    = false;
        $copy->ad_checked_by = null;
        $copy->ad_checked_at = null;
        $copy->save();

        return response()->json(['success' => true, 'row' => $copy]);
    }

    /**
     * Toggle (or explicitly set) the CHECK state of a row. Stamps the
     * acting user + time onto the row and appends an immutable entry to
     * video_ads_master_check_history so the full audit trail is preserved.
     */
    public function toggleCheck(Request $request, $id)
    {
        $row = VideoAdsMaster::findOrFail($id);

        // If the client sends an explicit `is_checked`, honour it; otherwise
        // flip whatever the current state is.
        $newState = $request->has('is_checked')
            ? $request->boolean('is_checked')
            : !$row->is_checked;

        $username = Auth::user()?->name ?? 'System';
        $now      = now();

        $row->is_checked = $newState;
        $row->checked_by = $newState ? $username : null;
        $row->checked_at = $newState ? $now : null;
        $row->save();

        VideoAdsMasterCheckHistory::create([
            'video_ads_master_id' => $row->id,
            'is_checked'          => $newState,
            'action'              => $newState ? 'checked' : 'unchecked',
            'username'            => $username,
            'created_at'          => $now,
        ]);

        return response()->json(['success' => true, 'row' => $row]);
    }

    /**
     * Toggle (or explicitly set) the AD state of a row. Stamps the acting
     * user + time onto the row so the grid can show who ticked the "ad"
     * checkbox and when.
     */
    public function toggleAdCheck(Request $request, $id)
    {
        $row = VideoAdsMaster::findOrFail($id);

        $newState = $request->has('ad_checked')
            ? $request->boolean('ad_checked')
            : !$row->ad_checked;

        $username = Auth::user()?->name ?? 'System';
        $now      = now();

        $row->ad_checked    = $newState;
        $row->ad_checked_by = $newState ? $username : null;
        $row->ad_checked_at = $newState ? $now : null;
        $row->save();

        return response()->json(['success' => true, 'row' => $row]);
    }

    /**
     * Return the full check/uncheck audit trail for a row, newest first.
     */
    public function checkHistory($id)
    {
        $row = VideoAdsMaster::findOrFail($id);

        $history = $row->checkHistory()->get()->map(function ($h) {
            return [
                'action'     => $h->action,
                'is_checked' => (bool) $h->is_checked,
                'username'   => $h->username,
                'created_at' => optional($h->created_at)->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json(['success' => true, 'history' => $history]);
    }

    /**
     * Create (or no-op upsert) an AUDIENCE option. Used by both the Manage
     * Audiences modal and the inline "type a new tag" Select2 flow.
     */
    public function storeAudienceOption(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Name is empty.'], 422);
        }

        $option = VideoAdAudienceOption::firstOrCreate(
            ['name' => $name],
            ['is_default' => false]
        );

        return response()->json([
            'success' => true,
            'option'  => $option->only(['id', 'name']),
            'options' => $this->audienceOptionsPayload(),
        ]);
    }

    /**
     * Rename an AUDIENCE option and rewrite every row that uses the old
     * name so tags stay consistent across the grid.
     */
    public function updateAudienceOption(Request $request, $id)
    {
        $option = VideoAdAudienceOption::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $newName = trim($data['name']);
        if ($newName === '') {
            return response()->json(['success' => false, 'message' => 'Name is empty.'], 422);
        }

        $exists = VideoAdAudienceOption::where('name', $newName)
            ->where('id', '!=', $option->id)
            ->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'That audience already exists.'], 422);
        }

        $oldName = $option->name;
        if ($oldName === $newName) {
            return response()->json([
                'success' => true,
                'option'  => $option->only(['id', 'name']),
                'options' => $this->audienceOptionsPayload(),
            ]);
        }

        DB::transaction(function () use ($option, $oldName, $newName) {
            $option->name = $newName;
            $option->save();

            // Rewrite tags on every row that contains the old name.
            VideoAdsMaster::whereNotNull('audience')
                ->where('audience', '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($oldName, $newName) {
                    foreach ($rows as $row) {
                        $tags = $this->splitTagNames($row->audience);
                        if (!in_array($oldName, $tags, true)) {
                            continue;
                        }
                        $tags = array_map(
                            fn ($t) => $t === $oldName ? $newName : $t,
                            $tags
                        );
                        // De-dupe while preserving order.
                        $tags = array_values(array_unique($tags));
                        $row->audience = $this->joinTagNames($tags);
                        $row->save();
                    }
                });
        });

        return response()->json([
            'success' => true,
            'option'  => $option->fresh()->only(['id', 'name']),
            'options' => $this->audienceOptionsPayload(),
        ]);
    }

    /**
     * Delete an AUDIENCE option and strip that tag from every row that
     * currently uses it.
     */
    public function destroyAudienceOption($id)
    {
        $option = VideoAdAudienceOption::findOrFail($id);
        $name   = $option->name;

        DB::transaction(function () use ($option, $name) {
            $option->delete();

            VideoAdsMaster::whereNotNull('audience')
                ->where('audience', '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($name) {
                    foreach ($rows as $row) {
                        $tags = $this->splitTagNames($row->audience);
                        if (!in_array($name, $tags, true)) {
                            continue;
                        }
                        $tags = array_values(array_filter($tags, fn ($t) => $t !== $name));
                        $row->audience = $this->joinTagNames($tags);
                        $row->save();
                    }
                });
        });

        return response()->json([
            'success' => true,
            'options' => $this->audienceOptionsPayload(),
        ]);
    }

    /**
     * Persist a HOOK option (create or upsert-by-name). Accepts optional
     * default `hook` (message) and `link` values which the form can use to
     * auto-fill those fields when a single hook tag is picked.
     *
     * Behaviour:
     *   - The `hook` / `link` fields are only updated when the request
     *     actually sends them. That way the inline cell-create flow (which
     *     posts just the name) doesn't wipe defaults set via the modal.
     */
    public function storeHookOption(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'hook' => 'nullable|string',
            'link' => 'nullable|string',
        ]);

        $name = $this->stripHookSuffix($data['name']);
        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Name is empty.'], 422);
        }

        $option = VideoAdsHookOption::firstOrCreate(['name' => $name]);

        // Only overwrite hook/link if the request actually carried them.
        $dirty = false;
        if ($request->has('hook')) { $option->hook = $data['hook'] ?? null; $dirty = true; }
        if ($request->has('link')) { $option->link = $data['link'] ?? null; $dirty = true; }
        if ($dirty) {
            $option->save();
        }

        return response()->json([
            'success' => true,
            'name'    => $option->name,
            'option'  => $option->only(['id', 'name', 'hook', 'link']),
            'options' => $this->hookOptionsPayload(),
        ]);
    }

    /**
     * Rename / update a HOOK option (defaults included) and rewrite every
     * row that uses the old name so tags stay consistent across the grid.
     */
    public function updateHookOption(Request $request, $id)
    {
        $option = VideoAdsHookOption::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'hook' => 'nullable|string',
            'link' => 'nullable|string',
        ]);

        $newName = $this->stripHookSuffix($data['name']);
        if ($newName === '') {
            return response()->json(['success' => false, 'message' => 'Name is empty.'], 422);
        }

        $exists = VideoAdsHookOption::where('name', $newName)
            ->where('id', '!=', $option->id)
            ->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'That hook already exists.'], 422);
        }

        $oldName = $option->name;

        DB::transaction(function () use ($option, $oldName, $newName, $request, $data) {
            $option->name = $newName;
            if ($request->has('hook')) {
                $option->hook = $data['hook'] ?? null;
            }
            if ($request->has('link')) {
                $option->link = $data['link'] ?? null;
            }
            $option->save();

            if ($oldName === $newName) {
                return;
            }

            VideoAdsMaster::whereNotNull('hook_name')
                ->where('hook_name', '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($oldName, $newName) {
                    foreach ($rows as $row) {
                        $tags = $this->splitTagNames($row->hook_name);
                        if (!in_array($oldName, $tags, true)) {
                            continue;
                        }
                        $tags = array_map(
                            fn ($t) => $t === $oldName ? $newName : $t,
                            $tags
                        );
                        $tags = array_values(array_unique($tags));
                        $row->hook_name = $this->joinTagNames($tags);
                        $row->save();
                    }
                });
        });

        return response()->json([
            'success' => true,
            'name'    => $option->fresh()->name,
            'option'  => $option->fresh()->only(['id', 'name', 'hook', 'link']),
            'options' => $this->hookOptionsPayload(),
        ]);
    }

    /**
     * Delete a HOOK option and strip that tag from every row that uses it.
     */
    public function destroyHookOption($id)
    {
        $option = VideoAdsHookOption::findOrFail($id);
        $name   = $option->name;

        DB::transaction(function () use ($option, $name) {
            $option->delete();

            VideoAdsMaster::whereNotNull('hook_name')
                ->where('hook_name', '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($name) {
                    foreach ($rows as $row) {
                        $tags = $this->splitTagNames($row->hook_name);
                        if (!in_array($name, $tags, true)) {
                            continue;
                        }
                        $tags = array_values(array_filter($tags, fn ($t) => $t !== $name));
                        $row->hook_name = $this->joinTagNames($tags);
                        $row->save();
                    }
                });
        });

        return response()->json([
            'success' => true,
            'options' => $this->hookOptionsPayload(),
        ]);
    }

    /**
     * Stream every row of `video_ads_master` as a CSV file using the exact
     * same headers as the import / sample-csv flow, so a user can
     * export → edit in Excel → re-import without any column gymnastics.
     *
     * The `id` and timestamps are deliberately omitted so re-importing
     * always creates new rows (the importer has no upsert key).
     */
    public function export()
    {
        $headers = ['target_type', 'name', 'channel', 'audience', 'hook_name', 'hook', 'link'];

        $filename = 'video-ads-master-' . date('Ymd-His') . '.csv';

        $callback = function () use ($headers) {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens UTF-8 CSVs without mojibake.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            // Stream in chunks to keep memory flat even on large tables.
            VideoAdsMaster::orderBy('id')->chunk(500, function ($rows) use ($out, $headers) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->target_type,
                        $row->name,
                        $row->channel,
                        $row->audience,
                        $row->hook_name,
                        $row->hook,
                        $row->link,
                    ]);
                }
            });

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Download a CSV template with the exact column headers used by the
     * import flow, plus three example rows so the user can see the expected
     * shape of each field.
     */
    public function sampleCsv()
    {
        $headers = ['target_type', 'name', 'channel', 'audience', 'hook_name', 'hook', 'link'];

        $rows = [
            ['sku',    'Drum Mic',       'B2B',      'Drummers / Studios',     'Curiosity Hook',  'Hear the difference in 5 seconds.',   'https://example.com/drum-mic'],
            ['parent', 'Guitar Family',  'Facebook', 'Hobbyist musicians',     'Pain Point',      'Tired of cables that crackle?',       'https://example.com/guitar'],
            ['group',  'Recording Gear', 'YouTube',  'Pro producers / labels', 'Story Hook',      'What is inside our flagship kit?',    'https://example.com/recording-gear'],
        ];

        $callback = function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, 'video-ads-master-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Import rows from a CSV file. Expected header row:
     *     target_type, name, channel, audience, hook_name, hook, link
     *
     * Rules:
     *   - target_type is required on every row; must be sku / parent / group.
     *   - Empty optional cells become NULL.
     *   - Each row creates a new record (no upsert key — duplicates allowed).
     *
     * Validation note: Laravel's `mimes:csv,txt` rule checks the *guessed*
     * MIME type, which is unreliable for CSVs in the wild (Excel saves as
     * application/vnd.ms-excel, Numbers / curl as text/plain, etc.). We
     * check the extension ourselves so every reasonable CSV gets through.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:5120',
        ]);

        $file = $request->file('file');
        $ext  = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'txt'], true)) {
            return response()->json([
                'success' => false,
                'message' => "Only .csv or .txt files are supported (got '." . $ext . "').",
            ], 422);
        }

        $path = $file->getRealPath();
        if (!$path) {
            return response()->json(['success' => false, 'message' => 'Uploaded file has no readable path.'], 422);
        }

        $handle = @fopen($path, 'r');
        if (!$handle) {
            return response()->json(['success' => false, 'message' => 'Unable to open uploaded file.'], 422);
        }

        $expected = ['target_type', 'name', 'channel', 'audience', 'hook_name', 'hook', 'link'];

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'CSV is empty.'], 422);
        }

        // Strip UTF-8 BOM (the Export endpoint writes one, and Excel does too)
        // from the very first header cell so it doesn't break header matching.
        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/u', '', (string) $headers[0]);
        }

        $headers = array_map(fn($h) => strtolower(trim((string) $h)), $headers);

        // Index lookup: header name → column position. Unknown headers are ignored.
        $idx = [];
        foreach ($expected as $name) {
            $pos = array_search($name, $headers, true);
            $idx[$name] = $pos === false ? null : $pos;
        }

        if ($idx['target_type'] === null) {
            fclose($handle);
            return response()->json([
                'success' => false,
                'message' => 'Required column "target_type" not found. Expected headers: ' . implode(', ', $expected),
            ], 422);
        }

        $validTypes = ['sku', 'parent', 'group'];
        $created    = 0;
        $skipped    = 0;
        $errors     = [];
        $lineNum    = 1; // header was line 1

        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue; // blank line
            }

            $get = function ($field) use ($row, $idx) {
                if ($idx[$field] === null) return null;
                $v = $row[$idx[$field]] ?? null;
                $v = is_string($v) ? trim($v) : $v;
                return ($v === '' || $v === null) ? null : $v;
            };

            $type = strtolower((string) $get('target_type'));

            if (!in_array($type, $validTypes, true)) {
                $skipped++;
                $errors[] = "Row {$lineNum}: invalid target_type '" . ($get('target_type') ?? '') . "' (must be sku / parent / group)";
                continue;
            }

            try {
                VideoAdsMaster::create([
                    'target_type' => $type,
                    'name'        => $get('name'),
                    'channel'     => $get('channel'),
                    'audience'    => $get('audience'),
                    'hook_name'   => $get('hook_name'),
                    'hook'        => $get('hook'),
                    'link'        => $get('link'),
                ]);
                $created++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Row {$lineNum}: " . $e->getMessage();
            }
        }
        fclose($handle);

        return response()->json([
            'success' => true,
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => array_slice($errors, 0, 20),
        ]);
    }

    /**
     * Seed video_ads_hook_options from any hook tags already stored on
     * video_ads_master rows, so the dropdown reflects existing data.
     */
    private function syncHookOptionsFromRows($rows): void
    {
        $seen = [];
        foreach ($rows as $row) {
            foreach ($this->splitTagNames($row->hook_name ?? null) as $name) {
                $clean = $this->stripHookSuffix($name);
                if ($clean !== '') {
                    $seen[$clean] = true;
                }
            }
        }

        foreach (array_keys($seen) as $name) {
            VideoAdsHookOption::firstOrCreate(['name' => $name]);
        }
    }

    /**
     * Seed video_ad_audience_options from any audience tags already stored
     * on video_ads_master rows, so the dropdown reflects existing data.
     */
    private function syncAudienceOptionsFromRows($rows): void
    {
        $seen = [];
        foreach ($rows as $row) {
            foreach ($this->splitTagNames($row->audience ?? null) as $name) {
                $seen[$name] = true;
            }
        }

        foreach (array_keys($seen) as $name) {
            VideoAdAudienceOption::firstOrCreate(
                ['name' => $name],
                ['is_default' => false]
            );
        }
    }

    private function hookOptionsPayload()
    {
        return VideoAdsHookOption::orderBy('name')->get(['id', 'name', 'hook', 'link']);
    }

    private function audienceOptionsPayload()
    {
        return VideoAdAudienceOption::orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Split a stored multi-tag cell into individual tags.
     * Multi-select is stored pipe-delimited ("A | B"); plain strings
     * (including ones with commas) stay as a single tag.
     */
    private function splitTagNames(?string $value): array
    {
        if ($value === null) {
            return [];
        }
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map(
                    fn ($v) => trim((string) $v),
                    $decoded
                )));
            }
        }

        if (str_contains($value, '|')) {
            return array_values(array_filter(array_map('trim', explode('|', $value))));
        }

        return [$value];
    }

    private function joinTagNames(array $tags): ?string
    {
        $tags = array_values(array_filter(array_map(
            fn ($t) => trim((string) $t),
            $tags
        )));

        return $tags === [] ? null : implode(' | ', $tags);
    }

    /** Drop a trailing " Hook" from option names for cleaner labels. */
    private function stripHookSuffix(?string $name): string
    {
        $name = trim((string) $name);
        $stripped = preg_replace('/\s+hook$/i', '', $name);
        $stripped = trim((string) $stripped);

        return $stripped !== '' ? $stripped : $name;
    }

    /**
     * Collapse duplicate hook types ("Content Creator" vs "Content Creator Hook")
     * into one option and rewrite row tags to the cleaned name.
     */
    private function dedupeHookOptions(): void
    {
        $options = VideoAdsHookOption::orderBy('id')->get();
        $keepers = []; // lowercased clean name => keeper model

        foreach ($options as $option) {
            $clean = $this->stripHookSuffix($option->name);
            if ($clean === '') {
                continue;
            }
            $key = mb_strtolower($clean);

            if (!isset($keepers[$key])) {
                if ($option->name !== $clean) {
                    $conflict = VideoAdsHookOption::where('name', $clean)
                        ->where('id', '!=', $option->id)
                        ->first();
                    if ($conflict) {
                        $this->rewriteHookTagOnRows($option->name, $clean);
                        if (!$conflict->hook && $option->hook) {
                            $conflict->hook = $option->hook;
                        }
                        if (!$conflict->link && $option->link) {
                            $conflict->link = $option->link;
                        }
                        $conflict->save();
                        $option->delete();
                        $keepers[$key] = $conflict;
                        continue;
                    }

                    $oldName = $option->name;
                    $option->name = $clean;
                    $option->save();
                    $this->rewriteHookTagOnRows($oldName, $clean);
                }
                $keepers[$key] = $option;
                continue;
            }

            $keeper = $keepers[$key];
            $this->rewriteHookTagOnRows($option->name, $keeper->name);
            if (!$keeper->hook && $option->hook) {
                $keeper->hook = $option->hook;
            }
            if (!$keeper->link && $option->link) {
                $keeper->link = $option->link;
            }
            $keeper->save();
            if ((int) $option->id !== (int) $keeper->id) {
                $option->delete();
            }
        }
    }

    private function rewriteHookTagOnRows(string $oldName, string $newName): void
    {
        if ($oldName === $newName || $oldName === '') {
            return;
        }

        VideoAdsMaster::whereNotNull('hook_name')
            ->where('hook_name', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($oldName, $newName) {
                foreach ($rows as $row) {
                    $tags = $this->splitTagNames($row->hook_name);
                    if (!in_array($oldName, $tags, true)) {
                        continue;
                    }
                    $tags = array_map(
                        fn ($t) => $t === $oldName ? $newName : $this->stripHookSuffix($t),
                        $tags
                    );
                    $tags = array_values(array_unique(array_filter($tags)));
                    $row->hook_name = $this->joinTagNames($tags);
                    $row->save();
                }
            });
    }
}
