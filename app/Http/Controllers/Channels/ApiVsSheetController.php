<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\ApiVsSheetSetting;
use App\Models\ChannelMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ApiVsSheetController extends Controller
{
    public const DOWNLOAD_OPTIONS = [
        'Sheet',
        'API Called Download',
    ];

    public const UPLOAD_OPTIONS = [
        'Sheet',
        'API Called Upload',
    ];

    public function tabulator()
    {
        return view('channels.api_vs_sheet.tabulator');
    }

    /**
     * Grid data: active Channel Master rows + download/upload source settings.
     */
    public function tabulatorChannelData()
    {
        return response()->json($this->buildChannelRows());
    }

    public function saveSetting(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'field' => ['required', Rule::in(['download_source', 'upload_source'])],
            'value' => [
                'nullable',
                'string',
                'max:50',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $field = (string) $request->input('field');
                    $allowed = $field === 'upload_source'
                        ? self::UPLOAD_OPTIONS
                        : self::DOWNLOAD_OPTIONS;
                    if (! in_array($value, $allowed, true)) {
                        $fail('Invalid '.$field.' value.');
                    }
                },
            ],
        ]);

        $channelId = (int) $request->input('channel_id');
        $field = (string) $request->input('field');
        $value = trim((string) $request->input('value', ''));
        $value = $value === '' ? null : $value;

        $record = ApiVsSheetSetting::query()->updateOrCreate(
            ['channel_id' => $channelId],
            [
                $field => $value,
                'updated_by' => optional(Auth::user())->name,
            ]
        );

        return response()->json([
            'success' => true,
            'channel_id' => $channelId,
            'download_source' => $record->download_source,
            'upload_source' => $record->upload_source,
        ]);
    }

    public function saveSheetLink(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'sheet_link' => 'nullable|string|max:2048',
        ]);

        $channelId = (int) $request->input('channel_id');
        $sheetLink = trim((string) $request->input('sheet_link', ''));
        $sheetLink = $sheetLink === '' ? null : $sheetLink;

        if ($sheetLink !== null && ! filter_var($sheetLink, FILTER_VALIDATE_URL)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid URL.',
            ], 422);
        }

        $channel = ChannelMaster::query()->findOrFail($channelId);
        $channel->sheet_link = $sheetLink;
        $channel->save();

        return response()->json([
            'success' => true,
            'channel_id' => $channelId,
            'sheet_link' => $sheetLink,
        ]);
    }

    public function savePriceApi2w(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'price_api_2w' => ['required', Rule::in(['Yes', 'No'])],
            'price_api_2w_sheet_link' => 'nullable|string|max:2048',
        ]);

        $channelId = (int) $request->input('channel_id');
        $choice = (string) $request->input('price_api_2w');
        $sheetLink = trim((string) $request->input('price_api_2w_sheet_link', ''));
        $sheetLink = $sheetLink === '' ? null : $sheetLink;

        if ($choice === 'Yes') {
            $sheetLink = null;
        } elseif ($sheetLink !== null && ! filter_var($sheetLink, FILTER_VALIDATE_URL)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid Sheet URL.',
            ], 422);
        }

        $record = ApiVsSheetSetting::query()->updateOrCreate(
            ['channel_id' => $channelId],
            [
                'price_api_2w' => $choice,
                'price_api_2w_sheet_link' => $sheetLink,
                'updated_by' => optional(Auth::user())->name,
            ]
        );

        return response()->json([
            'success' => true,
            'channel_id' => $channelId,
            'price_api_2w' => $record->price_api_2w,
            'price_api_2w_sheet_link' => $record->price_api_2w_sheet_link,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildChannelRows(): array
    {
        $channels = ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->orderBy('type')
            ->orderBy('channel')
            ->get(['id', 'channel', 'type', 'status', 'logo', 'sheet_link']);

        $settings = ApiVsSheetSetting::query()
            ->whereIn('channel_id', $channels->pluck('id'))
            ->get([
                'channel_id',
                'download_source',
                'upload_source',
                'price_api_2w',
                'price_api_2w_sheet_link',
            ])
            ->keyBy('channel_id');

        return $channels->map(function (ChannelMaster $c) use ($settings) {
            /** @var ApiVsSheetSetting|null $row */
            $row = $settings->get($c->id);

            return [
                'id' => $c->id,
                'channel' => $c->channel,
                'type' => $c->type,
                'status' => $c->status,
                'logo' => $c->logo,
                'sheet_link' => $c->sheet_link ?: null,
                'download_source' => $row?->download_source ?: null,
                'upload_source' => $row?->upload_source ?: null,
                'price_api_2w' => $row?->price_api_2w ?: null,
                'price_api_2w_sheet_link' => $row?->price_api_2w_sheet_link ?: null,
            ];
        })->values()->all();
    }
}
