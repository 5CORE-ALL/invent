<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChannelWiseController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    /**
     * Handle dynamic route parameters and return a view.
     */
    public function channel_wise_index(Request $request, $first = null, $second = null)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
    
        if ($first === "assets") {
            return redirect('home');
        }
    
        // ✅ Fetch data from ApiController
        $apiController = new ApiController();
        $response = $apiController->fetchExternalData();
        $responseData = $response->getData(true);
    
        // ✅ Check if 'data' key exists
        if (!isset($responseData['data']) || !is_array($responseData['data'])) {
            return response()->json(['error' => 'Invalid API response'], 500);
        }
    
        $data = $responseData['data'];
    
        // ✅ Paginate the array manually
        $perPage = 20;
        $currentPage = $request->query('page', 1);
        $offset = ($currentPage - 1) * $perPage;
    
        $paginatedData = new LengthAwarePaginator(
            array_slice($data, $offset, $perPage),
            count($data),
            $perPage,
            $currentPage,
            ['path' => url()->current()]
        );
    
        return view($first . '.' . $second, [
            'mode' => $mode,
            'demo' => $demo,
            'channelSheets' => $paginatedData,
        ]);
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

    public function destroy(Request $request)
    {
        // Delete channel from database
    }

}
