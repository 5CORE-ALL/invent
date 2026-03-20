<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\ApprovalsChannelMaster;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OpportunityController extends Controller
{
    public function index(){
        return view('channels.active-channel.opportunity');
    }

    public function getOpportunitiesData()
    {
        $data = Opportunity::select(
                    'id',
                    'channel_name',
                    'type',
                    'regn_link',
                    'status',
                    'aa_stage',
                    'priority',
                    'item_sold',
                    'link_as_customer',
                    'last_year_traffic',
                    'current_traffic',
                    'us_presence',
                    'us_visitor_count',
                    'comm_chgs',
                    'current_status',
                    'final',
                    'date',
                    'email',
                    'remarks',
                    'sign_up_page_link',
                    'followup_dt',
                    'masum_comment'
                )->get();

        return response()->json($data);
    }


    public function saveOpportunity(Request $request)
    {
        $validated = $request->validate([
            'field' => 'required|string',
            'value' => 'nullable'
        ]);

        $opportunity = Opportunity::find($request->id);

        if (!$opportunity) {
            return response()->json(['success' => false, 'message' => 'Opportunity not found'], 404);
        }

        $opportunity->{$request->field} = $request->value;
        $opportunity->save();

        if (!empty($opportunity->aa_stage) && $opportunity->aa_stage !== 'null' && $opportunity->aa_stage !== 'not_applicable') {
            
            $opportunitiesWithStage = Opportunity::whereNotNull('aa_stage')
                ->where('aa_stage', '!=', '')
                ->where('aa_stage', '!=', 'not_applicable')
                ->get();

            foreach ($opportunitiesWithStage as $opp) {
                ApprovalsChannelMaster::updateOrCreate(
                    ['channel_name' => $opp->channel_name],
                    [
                        'type' => $opp->type,
                        'channel_name' => $opp->channel_name,
                        'regn_link' => $opp->regn_link,
                        'status' => $opp->status,
                        'aa_stage' => $opp->aa_stage,
                        'date' => $opp->date,
                        'email_userid' => $opp->email,
                        'remarks' => $opp->remarks,
                    ]
                );
            }
        }

        return response()->json(['success' => true, 'id' => $opportunity->id]);
    }

    public function deleteOpportunities(Request $request)
    {
        $ids = $request->ids;
        Opportunity::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => 'Deleted successfully.']);
    }


    public function importOpportunities(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls'
        ]);

        $file = $request->file('excel_file');
        $spreadsheet = IOFactory::load($file->getPathName());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $headers = array_filter(array_map('strtolower', array_map('trim', $rows[0])));
        unset($rows[0]);

        foreach ($rows as $row) {
            if (empty($row[1])) {
                continue;
            }

            $row = array_slice($row, 0, count($headers));

            $data = array_combine($headers, $row);

            Opportunity::updateOrCreate(
                ['channel_name' => $data['channel_name']],
                [
                    'type' => $data['type'] ?? '',
                    'regn_link' => $data['regn_link'] ?? '',
                    'status' => $data['status'] ?? '',
                    'aa_stage' => $data['aa_stage'] ?? '',
                    'priority' => $data['priority'] ?? '',
                    'item_sold' => $data['item_sold'] ?? '',
                    'link_as_customer' => $data['link_as_customer'] ?? '',
                    'last_year_traffic' => $data['last_year_traffic'] ?? '',
                    'current_traffic' => $data['current_traffic'] ?? '',
                    'us_presence' => $data['us_presence'] ?? '',
                    'us_visitor_count' => $data['us_visitor_count'] ?? '',
                    'comm_chgs' => $data['comm_chgs'] ?? '',
                    'current_status' => $data['current_status'] ?? '',
                    'final' => $data['final'] ?? '',
                    'date' => !empty($data['date']) ? $data['date'] : null,
                    'email' => $data['email'] ?? '',
                    'remarks' => $data['remarks'] ?? '',
                    'sign_up_page_link' => $data['sign_up_page_link'] ?? '',
                    'followup_dt' => !empty($data['followup_dt']) ? $data['followup_dt'] : null,
                    'masum_comment' => $data['masum_comment'] ?? '',
                ]
            );
        }

        return back()->with('success', 'Opportunities Imported Successfully!');
    }

    public function exportOpportunities()
    {
        $opportunities = Opportunity::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = [
            'Type', 'Channel Name', 'Regn Link', 'Status', 'AA Stage', 'Priority',
            'Item Sold', 'Link as Customer', 'Last Year Traffic', 'Current Traffic',
            'US Presence', 'US Visitor Count', 'Comm Chgs', 'Current Status', 'Final',
            'Date', 'Email', 'Remarks', 'Sign Up Page Link', 'Followup Dt', 'Masum Comment'
        ];

        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($opportunities as $opportunity) {
            $sheet->fromArray([
                $opportunity->type,
                $opportunity->channel_name,
                $opportunity->regn_link,
                $opportunity->status,
                $opportunity->aa_stage,
                $opportunity->priority,
                $opportunity->item_sold,
                $opportunity->link_as_customer,
                $opportunity->last_year_traffic,
                $opportunity->current_traffic,
                $opportunity->us_presence,
                $opportunity->us_visitor_count,
                $opportunity->comm_chgs,
                $opportunity->current_status,
                $opportunity->final,
                $opportunity->date,
                $opportunity->email,
                $opportunity->remarks,
                $opportunity->sign_up_page_link,
                $opportunity->followup_dt,
                $opportunity->masum_comment,
            ], NULL, 'A' . $rowIndex);
            $rowIndex++;
        }

        // Output Download
        $fileName = 'Opportunities_Export.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'. $fileName .'"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }



}
