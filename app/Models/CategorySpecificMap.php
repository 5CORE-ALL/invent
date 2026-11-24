<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which eBay category an MFR category (the Category list page) is the same as.
 * One row per MFR category, written when somebody confirms the match on the Category Specifics page.
 */
class CategorySpecificMap extends Model
{
    protected $table = 'category_specific_maps';

    protected $fillable = ['mfr_key', 'mfr_name', 'ebay_path', 'ebay_category_id', 'confirmed_by'];

    /** Deploys do not run migrations, so the table is also created the first time it is needed. */
    public static function ensureTable(): void
    {
        if (Schema::hasTable('category_specific_maps')) {
            return;
        }
        Schema::create('category_specific_maps', function (Blueprint $table) {
            $table->id();
            $table->string('mfr_key', 191)->unique();
            $table->string('mfr_name', 191);
            $table->string('ebay_path', 500);
            $table->string('ebay_category_id', 64)->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamps();
        });
    }

    public static function keyFor(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name));
    }
}
