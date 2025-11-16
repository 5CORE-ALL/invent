<?php

namespace App\Http\Controllers\Channels\ReviewMaster;

use App\Http\Controllers\Controller;
use App\Models\AmazonProductReview;
use App\Models\ChannelMaster;
use Illuminate\Support\Str;


class ReviewDashboardController extends Controller
{
    public function index()
    {
        return view('channels.review-masters.review-dashboard');
    }


    public function getReviewDataChannelBased()
    {
        $channels = ChannelMaster::pluck('channel')->all();
        $reviews = AmazonProductReview::all();

        $data = [];

        foreach ($channels as $channel) {

            $channelReviews = $reviews->filter(function ($review) use ($channel) {
                return trim(Str::lower($review->channel)) === trim(Str::lower($channel));
            });

            $actionPendingCount = $channelReviews->filter(fn($r) => trim(Str::lower($r->action)) === 'pending')->count();
            $ratingsLessThanComp = $channelReviews->filter(fn($r) => is_numeric($r->product_rating) && is_numeric($r->comp_rating) && $r->product_rating < $r->comp_rating)->count();
            $ratingLessThan3_5 = $channelReviews->filter(fn($r) => is_numeric($r->product_rating) && $r->product_rating < 3.5)->count();
            $ratingLessThan4 = $channelReviews->filter(fn($r) => is_numeric($r->product_rating) && $r->product_rating < 4)->count();
            $ratingLessThan4_5 = $channelReviews->filter(fn($r) => is_numeric($r->product_rating) && $r->product_rating < 4.5)->count();
            $ratingLessThan4_5 = $channelReviews->filter(fn($r) => is_numeric($r->product_rating) && $r->product_rating < 4.5)->count();

            $row = [];
            $row['channel_name'] = $channel;
            $row['action_pending_count'] = $actionPendingCount;
            $row['rating_less_than_comp'] = $ratingsLessThanComp;
            $row['rating_less_than_3_5'] = $ratingLessThan3_5;
            $row['rating_less_than_4'] = $ratingLessThan4;
            $row['rating_less_than_4_5'] = $ratingLessThan4_5;
            $row['negation_l90'] = $channelReviews->sum('negation_l90');
            $row['total_negation_l90'] = $channelReviews->sum('negation_l90') ?? 0;
            $row['neg_seller_feedback'] = "";

            $data[] = $row;
        }

        return response()->json(['data' => $data]);
    }
}
