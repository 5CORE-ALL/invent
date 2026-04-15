<?php

namespace App\Http\Controllers;

class AmazonUtilizedKwSheetController extends Controller
{
    /**
     * Tab-separated sheet view; loads rows from /amazon/utilized/kw/ads/data in the browser.
     */
    public function index()
    {
        return view('campaign.amazon.amazon-utilized-kw-sheet');
    }
}
