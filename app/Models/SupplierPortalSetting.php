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
        'contact_email',
        'footer_tagline',
    ];

    public static function ensureDatabaseReachable(): void
    {
        $host = (string) config('database.connections.mysql.host', '127.0.0.1');
        $port = (int) config('database.connections.mysql.port', 3306);
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, 2);
        if (is_resource($fp)) {
            fclose($fp);

            return;
        }

        abort(503, 'Supplier Portal cannot reach MySQL. Start MySQL in XAMPP and refresh.');
    }

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
            'contact_email' => 'partners@5core.com',
            'footer_tagline' => 'Sound of India, Made for USA',
        ]);
    }
}
