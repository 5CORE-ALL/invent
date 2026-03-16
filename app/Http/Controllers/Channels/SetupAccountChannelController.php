<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\SetupAccountChannelMaster;
use Illuminate\Http\Request;

class SetupAccountChannelController extends Controller
{
    public function index(){
        return view('channels.active-channel.setup-account');
    }

    public function fetchSetupAccountData()
    {
        $data = SetupAccountChannelMaster::with('channel')->get()->map(function ($item) {
            return [
                'setupAccountId' => $item->id,
                'type' => $item->type ?? '',
                'status' => $item->status ?? '',
                'login_link' => $item->login_link ?? '',
                'email_userid' => $item->email_userid ?? '',
                'password' => $item->password ?? '',
                'remarks' => $item->remarks ?? '',
                'channel_name' => $item->channel_name
            ];
        });

        return response()->json($data);
    }

    public function saveSetupAccountData(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'field' => 'required|string',
            'value' => 'nullable'
        ]);

        $record = SetupAccountChannelMaster::find($request->id);
        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        }

        $record->{$request->field} = $request->value;
        $record->save();

        if ($request->field === 'status' && $request->value === 'active') {
            if ($record->type && $record->channel_name) {
                $channelMaster = ChannelMaster::find($record->channel_name);

                if ($channelMaster) {
                    $channelMaster->type = $record->type;
                    $channelMaster->save();
                } else {
                    ChannelMaster::create([
                        'type' => $record->type,
                        'channel' => $record->channel_name ?? 'Unknown'
                    ]);
                }
            }
        }

        return response()->json(['success' => true]);
    }

}
