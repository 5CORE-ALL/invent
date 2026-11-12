<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentVerification extends Model
{
    protected $table = 'payment_verifications';

    protected $fillable = [
        'channel_key',
        'channel',
        'pmt_recd',
        'bank_account',
        'link',
        'updated_by',
    ];

    /** Lowercase letters/digits only, so "eBay 3" and "ebay3" are the same channel. */
    public static function keyFor(string $channel): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', trim($channel)));
    }
}
