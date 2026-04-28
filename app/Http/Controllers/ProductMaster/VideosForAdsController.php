<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\VideoForAd;
use Illuminate\Http\Request;

class VideosForAdsController extends Controller
{
    public function index()
    {
        return view('videos-for-ads');
    }

    public function getData()
    {
        $records = VideoForAd::orderBy('sku')->get();
        return response()->json(['success' => true, 'data' => $records]);
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
