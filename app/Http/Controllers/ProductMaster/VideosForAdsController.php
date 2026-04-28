<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\VideoForAd;
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
            'sku' => 'required|string|max:255',
        ]);

        $fields = [
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
        foreach ($fields as $field) {
            $data[$field] = $request->input($field, '');
        }

        VideoForAd::updateOrCreate(
            ['sku' => $data['sku']],
            $data
        );

        return response()->json(['success' => true, 'message' => 'Saved successfully']);
    }

    public function destroy($id)
    {
        $record = VideoForAd::findOrFail($id);
        $record->delete();
        return response()->json(['success' => true, 'message' => 'Deleted successfully']);
    }
}
