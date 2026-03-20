<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\ApprovalsChannelMaster;
use App\Models\SetupAccountChannelMaster;
use Illuminate\Http\Request;

class ApprovalsChannelMasterController extends Controller
{
    public function index(){
        return view('channels.active-channel.approvals');
    }

    public function fetchApprovalsData(Request $request)
    {
        $data = ApprovalsChannelMaster::with('channelMaster')->get()->map(function ($row) {
            return [
                'approval_id' => $row->id,
                'type' => $row->type,
                'channel_name' => $row->channel_name ?? '',
                'regn_link' => $row->regn_link,
                'status' => $row->status,
                'aa_stage' => $row->aa_stage,
                'date' => $row->date,
                'login_link' => $row->login_link,
                'email_userid' => $row->email_userid,
                'password' => $row->password,
                'last_date' => $row->last_date,
                'remarks' => $row->remarks,
                'next_date' => $row->next_date,
            ];
        });

        return response()->json($data);
    }

    public function saveApprovalsData(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'field' => 'required|string',
            'value' => 'nullable'
        ]);

        $record = ApprovalsChannelMaster::find($request->id);
        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        }

        $record->{$request->field} = $request->value;
        $record->save();

        // Check if field is 'aa_stage' and value is 'approved'
        if ($request->field === 'aa_stage' && $request->value === 'approved') {
            $setupRecord = SetupAccountChannelMaster::firstOrNew(['channel_name' => $record->channel_name]);

            $setupRecord->type = $record->type;
            $setupRecord->status = $record->status;
            $setupRecord->login_link = $record->login_link;
            $setupRecord->email_userid = $record->email_userid;
            $setupRecord->password = $record->password;
            $setupRecord->remarks = $record->remarks;
            $setupRecord->channel_name = $record->channel_name;

            $setupRecord->save();
        }

        return response()->json(['success' => true]);
    }

    public function deleteApprovals(Request $request)
    {
        $ids = $request->ids;
        ApprovalsChannelMaster::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => 'Deleted successfully.']);
    }


}
