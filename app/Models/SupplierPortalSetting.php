<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPortalSetting extends Model
{
    protected $table = 'supplier_portal_settings';

    protected $fillable = [
        'company_name',
        'hero_title',
        'hero_subtitle',
        'hero_image_path',
        'announcement',
        'contact_email',
        'footer_tagline',
    ];

    public static function current(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'company_name' => '5 Core',
            'hero_title' => 'Welcome to 5 Core Supplier Portal',
            'hero_subtitle' => 'Your one-stop destination for brand assets, packaging designs, logos and more.',
            'announcement' => 'New packaging designs are available. Please download the latest files before production.',
            'contact_email' => 'partners@5core.com',
            'footer_tagline' => 'Sound of India, Made for USA',
        ]);
    }
}
