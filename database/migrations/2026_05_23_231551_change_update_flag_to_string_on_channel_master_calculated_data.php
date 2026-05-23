<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "Update" column on /all-marketplace-master now holds a short tag
     * ("A" / "S") chosen by the user in the channel edit modal. The slow path
     * stores that tag in channel_master.update (already varchar). The fast
     * path mirrors it into channel_master_calculated_data.update_flag, which
     * was an int — casting "A" / "S" to 0 silently lost the value. Widen the
     * column to varchar so the mirror works.
     */
    public function up(): void
    {
        if (!Schema::hasTable('channel_master_calculated_data')) {
            return;
        }

        // Widen + null-ify in one ALTER. The table is truncated and rebuilt every
        // time channel:calculate-data runs, so the existing int values are scratch
        // data and safe to overwrite. We use a raw ALTER (instead of doctrine/dbal
        // ->change()) so this works on installs that don't pull doctrine/dbal.
        DB::statement('ALTER TABLE `channel_master_calculated_data` MODIFY `update_flag` VARCHAR(8) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('channel_master_calculated_data')) {
            return;
        }

        // Drop any non-numeric values so the int reversion can't fail.
        DB::statement('UPDATE `channel_master_calculated_data` SET `update_flag` = NULL WHERE `update_flag` IS NOT NULL AND `update_flag` NOT REGEXP "^-?[0-9]+$"');
        DB::statement('ALTER TABLE `channel_master_calculated_data` MODIFY `update_flag` INT NOT NULL DEFAULT 0');
    }
};
