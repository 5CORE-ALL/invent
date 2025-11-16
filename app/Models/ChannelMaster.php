<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelMaster extends Model
{
    use HasFactory;

    protected $table = 'channel_master'; // Define table name if different from default

    protected $fillable = [
        'channel',
        'sheet_link',
        'type',
        'status',
        'executive',
        'b_link',
        's_link',
        'user_id',
        'action_req',
        'channel_percentage'
    ];

    /**
     * Relationship with User (Assuming `user_id` references `id` in `users` table)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function shippingRates()
    {
        return $this->hasMany(ShippingRate::class, 'channel_id');
    }
}
