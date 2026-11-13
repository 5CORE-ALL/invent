<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The Google account that owns the Calendar events / Meet spaces created by the Meeting section.
 * The refresh token is encrypted at rest (APP_KEY) and never serialised.
 */
class GoogleMeetConnection extends Model
{
    protected $fillable = ['email', 'refresh_token', 'scopes', 'connected_by'];

    protected $hidden = ['refresh_token'];

    protected $casts = [
        'refresh_token' => 'encrypted',
    ];

    public function scopeList(): array
    {
        return array_values(array_filter(preg_split('/\s+/', (string) $this->scopes) ?: []));
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopeList(), true);
    }
}
