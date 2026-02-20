<?php     

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE reverb_products MODIFY sku VARCHAR(255)");
        
        DB::statement("ALTER TABLE reverb_products MODIFY bump_bid VARCHAR(50)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // wapas purani setting par
        DB::statement("ALTER TABLE reverb_products MODIFY bump_bid VARCHAR(255)");
        // SKU wapas pehle jaisa (agar koi issue ho)
    }
};