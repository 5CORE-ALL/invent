<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\VideoForAd;
use App\Models\VideoAdAudienceOption;
use App\Support\VideoThumbnailUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VideosForAdsController extends Controller
{
    public function index()
    {
        return view('videos-for-ads');
    }

    public function getData()
    {
        $records = VideoForAd::orderBy('sku')->get();

        // Fetch parent + image from product_master keyed by SKU
        $masters = DB::table('product_master')
            ->select('sku', 'parent', 'Values')
            ->get()
            ->keyBy('sku');

        $data = $records->map(function ($row) use ($masters) {
            $item = $row->toArray();
            $master = $masters->get($row->sku);

            $item['parent_name'] = $master ? $master->parent : null;

            // Resolve image_path from Values JSON (same logic as ProductMasterController)
            $imagePath = null;
            if ($master && $master->Values) {
                $values = is_string($master->Values) ? json_decode($master->Values, true) : (array) $master->Values;
                $localImage = $values['image_path'] ?? null;
                if ($localImage) {
                    $imagePath = '/' . ltrim($localImage, '/');
                }
            }
            $item['image_path'] = $imagePath;

            return $item;
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sku' => 'nullable|string|max:255',
        ]);

        // Integer fields must default to 0, not empty string
        $intFields = ['appr_s', 'appr_i', 'appr_n'];

        $stringFields = [
            'ads_status',
            'category',
            'video_thumbnail',
            'video_url',
            'ads_topic_story',
            'ads_what',
            'ads_why_purpose',
            'ads_audience',
            'ads_benefit_audience',
            'ads_location',
            'ads_language',
            'ads_script_link',
            'ads_script_link_status',
            'ads_video_en_link',
            'ads_video_en_link_status',
            'ads_video_es_link',
            'ads_video_es_link_status',
        ];

        $data = ['sku' => trim($request->sku)];
        foreach ($stringFields as $field) {
            $data[$field] = $request->input($field, '');
        }
        if ($data['video_thumbnail'] !== '') {
            $data['video_thumbnail'] = VideoThumbnailUrl::normalize($data['video_thumbnail']);
        }
        foreach ($intFields as $field) {
            $data[$field] = (int) $request->input($field, 0);
        }

        VideoForAd::updateOrCreate(
            ['sku' => $data['sku']],
            $data
        );

        return response()->json(['success' => true, 'message' => 'Saved successfully']);
    }

    public function getAudienceOptions()
    {
        $options = VideoAdAudienceOption::orderBy('is_default', 'desc')->orderBy('name')->pluck('name');
        return response()->json(['success' => true, 'options' => $options]);
    }

    public function storeAudienceOption(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);
        $name = trim($request->name);

        $option = VideoAdAudienceOption::firstOrCreate(
            ['name' => $name],
            ['is_default' => false]
        );

        return response()->json(['success' => true, 'name' => $option->name]);
    }

    public function destroy($id)
    {
        $record = VideoForAd::findOrFail($id);
        $record->delete();
        return response()->json(['success' => true, 'message' => 'Deleted successfully']);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls']);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, ['xlsx', 'xls'])) {
            return response()->json(['success' => false, 'message' => 'Please export a CSV from Excel and re-upload, or use the CSV template.'], 422);
        }

        $handle = fopen($file->getRealPath(), 'r');
        $headers = null;
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        $allowedFields = [
            'sku','ads_status','appr_s','appr_i','appr_n','category',
            'video_thumbnail','video_url','ads_topic_story','ads_what',
            'ads_why_purpose','ads_audience','ads_benefit_audience',
            'ads_language','ads_script_link','ads_video_en_link',
        ];

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if ($headers === null) {
                $headers = array_map('trim', $row);
                continue;
            }

            $data = array_combine($headers, array_pad($row, count($headers), ''));
            if (empty(trim($data['sku'] ?? ''))) {
                $skipped++;
                continue;
            }

            $record = [];
            foreach ($allowedFields as $f) {
                if (array_key_exists($f, $data)) {
                    $record[$f] = trim($data[$f]);
                }
            }
            if (! empty($record['video_thumbnail'])) {
                $record['video_thumbnail'] = VideoThumbnailUrl::normalize($record['video_thumbnail']);
            }

            try {
                VideoForAd::updateOrCreate(['sku' => trim($data['sku'])], $record);
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = 'SKU ' . ($data['sku'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        fclose($handle);

        return response()->json([
            'success'  => true,
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 10),
        ]);
    }
}
