<?php

namespace App\Http\Controllers;

use App\Models\ChannelMaster;
use App\Models\VideoAdsHookOption;
use App\Models\VideoAdsMaster;
use Illuminate\Http\Request;

class VideoAdsMasterController extends Controller
{
    public function index()
    {
        return view('video-ads-master');
    }

    /**
     * Returns the full grid payload in one call:
     *   - rows         : video_ads_master records (with timestamps)
     *   - channels     : list of channels from channel_master (CHANNEL dropdown)
     *   - hook_options : list of HOOK NAME options (from video_ads_hook_options)
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

        $hookOptions = VideoAdsHookOption::orderBy('name')->pluck('name');

        return response()->json([
            'success'      => true,
            'rows'         => $rows,
            'channels'     => $channels,
            'hook_options' => $hookOptions,
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
            'hook_name'    => 'nullable|string|max:255',
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
            'hook_name'    => 'sometimes|nullable|string|max:255',
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
        $copy->save();

        return response()->json(['success' => true, 'row' => $copy]);
    }

    /**
     * Persist a new HOOK NAME option. Returns the canonical name back to the
     * client so the new value flows straight into the dropdown.
     */
    public function storeHookOption(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $name = trim($request->input('name'));
        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Name is empty.'], 422);
        }

        $option = VideoAdsHookOption::firstOrCreate(['name' => $name]);

        return response()->json([
            'success' => true,
            'name'    => $option->name,
            'options' => VideoAdsHookOption::orderBy('name')->pluck('name'),
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
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            return response()->json(['success' => false, 'message' => 'Unable to read uploaded file.'], 422);
        }

        $expected = ['target_type', 'name', 'channel', 'audience', 'hook_name', 'hook', 'link'];

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'CSV is empty.'], 422);
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
}
