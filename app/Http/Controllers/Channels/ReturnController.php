<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ReturnController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    /**
     * Handle dynamic route parameters and return a view.
     */
    public function return_master_index(Request $request, $first = null, $second = null)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        if ($first === "assets") {
            return redirect('home');
        }

        // return view($first, compact('mode', 'demo', 'second', 'channels'));
        return view('channels.return-analysis', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewReturnData(Request $request)
    {
        // Fetch data from the Google Sheet using the ApiController method
        $response = $this->apiController->fetchDataFromChannelMasterGoogleSheet();

        // Check if the response is successful
        if ($response->getStatusCode() === 200) {
            $data = $response->getData(); // Get the JSON data from the response
            
            // Filter out rows where both Parent and (Child) sku are empty
            $filteredData = array_filter($data->data, function($item) {
                $channel = $item->{'Channel '} ?? '';
                
                // Keep the row if either channel is not empty
                return !(empty(trim($channel)));
            });
    
            // Re-index the array after filtering
            $filteredData = array_values($filteredData);
    
            // Return the filtered data
            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => $filteredData,
                'status' => 200
            ]);
        } else {
            // Handle the error if the request failed
            return response()->json([
                'message' => 'Failed to fetch data from Google Sheet',
                'status' => $response->getStatusCode()
            ], $response->getStatusCode());
        }
    }

    /**
     * Store a newly created channel in storage.
     */
    public function store(Request $request)
    {
        Log::info('Request Data:', $request->all());

        // Validate Request Data
        $validatedData = $request->validate([
            'channel' => 'required|string',
            'status' => 'required|in:Active,In Active,To Onboard,In Progress',
            'executive' => 'nullable|string',
            'b_link' => 'nullable|string',
            's_link' => 'nullable|string',
            'user_id' => 'nullable|string',
            'action_req' => 'nullable|string',
        ]);
        // Save Data to Database
        try {
            $channel = ChannelMaster::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'channel saved successfully',
                'data' => $channel
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving channel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save channel. Please try again.'
            ], 500);
        }
    }

    /**
     * Store a update channel in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'channel' => 'required|string|max:255',
            'status' => 'required|string',
            'executive' => 'nullable|string',
            'b_link' => 'required|string',
            's_link' => 'nullable|string',
            'user_id' => 'required|string',
            'action_req' => 'nullable|string',
        ]);

        try {
            $channel = ChannelMaster::findOrFail($id);
            $channel->update([
                'channel' => $request->channel,
                'status' => $request->status,
                'executive' => $request->executive,
                'b_link' => $request->b_link,
                's_link' => $request->s_link,
                'user_id' => $request->user_id,
                'action_req' => $request->action_req,
            ]);

            return response()->json(['success' => true, 'message' => 'Channel updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update channel']);
        }
    }
    public function getChannelCounts()
    {
        // Fetch counts from the database
        $totalChannels = DB::table('channel_master')->count();
        $activeChannels = DB::table('channel_master')->where('status', 'Active')->count();
        $inactiveChannels = DB::table('channel_master')->where('status', 'In Active')->count();
    
        return response()->json([
            'success' => true,
            'totalChannels' => $totalChannels,
            'activeChannels' => $activeChannels,
            'inactiveChannels' => $inactiveChannels,
        ]);
    }

    public function destroy(Request $request)
    {
        // Delete channel from database
    }



}
