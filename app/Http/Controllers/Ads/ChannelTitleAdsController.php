<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChannelTitleAdsController extends Controller
{
    public function show(Request $request, string $channel): View
    {
        $title = trim(strip_tags((string) $request->query('title', '')));
        if ($title === '' || mb_strlen($title) > 80) {
            $title = trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $channel));
        }
        if ($title === '') {
            $title = 'Channel';
        }

        return view('ads.channel-title', [
            'title' => $title,
        ]);
    }
}
