<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_data')) {
            Schema::table('driver_data', function (Blueprint $table) {
                if ($this->hasForeignKey('driver_data', 'driver_data_created_by_foreign')) {
                    $table->dropForeign('driver_data_created_by_foreign');
                }
            });
        }

        if (Schema::hasTable('driver_folders')) {
            Schema::table('driver_folders', function (Blueprint $table) {
                if ($this->hasForeignKey('driver_folders', 'driver_folders_created_by_foreign')) {
                    $table->dropForeign('driver_folders_created_by_foreign');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('driver_data')) {
            Schema::table('driver_data', function (Blueprint $table) {
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('driver_folders')) {
            Schema::table('driver_folders', function (Blueprint $table) {
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    protected function hasForeignKey(string $table, string $foreignKey): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ? LIMIT 1',
            [$database, $table, $foreignKey, 'FOREIGN KEY']
        );

        return ! empty($result);
    }
};
