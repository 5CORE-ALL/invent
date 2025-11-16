<?php

namespace App\Http\Controllers\Listing;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;


class ListingManagerController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;

    }
    public function listingmaster()
    {
        return view('listingmaster'); // this will look for resources/views/listingmaster.blade.php
    }
    public function getViewListingMasterData(Request $request)
    {
        // Fetch data from the Google Sheet using the ApiController method
        $response = $this->apiController->fetchDataFromListingMasterGoogleSheet();

        // Check if the response is successful
        if ($response->getStatusCode() === 200) {
            $data = $response->getData(); // Get the JSON data from the response

            // Return the paginated data along with pagination metadata
            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => $data->data, // Return only the paginated data
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

}