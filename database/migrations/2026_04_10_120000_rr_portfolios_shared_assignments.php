<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rr_portfolios', function (Blueprint $table) {
            $table->id();
            $table->longText('html_content')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('source_format', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('rr_portfolio_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rr_portfolio_id')->constrained('rr_portfolios')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('fits')->default(false);
            $table->timestamps();

            $table->unique(['rr_portfolio_id', 'user_id']);
        });

        if (Schema::hasTable('user_rr_portfolios')) {
            $rows = DB::table('user_rr_portfolios')->get();
            foreach ($rows as $row) {
                $pid = DB::table('rr_portfolios')->insertGetId([
                    'html_content' => $row->html_content,
                    'original_filename' => $row->original_filename,
                    'source_format' => $row->source_format,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
                DB::table('rr_portfolio_user')->insert([
                    'rr_portfolio_id' => $pid,
                    'user_id' => $row->user_id,
                    'fits' => false,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
            Schema::drop('user_rr_portfolios');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rr_portfolio_user');
        Schema::dropIfExists('rr_portfolios');

        if (! Schema::hasTable('user_rr_portfolios')) {
            Schema::create('user_rr_portfolios', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->longText('html_content')->nullable();
                $table->string('original_filename')->nullable();
                $table->string('source_format', 32)->nullable();
                $table->timestamps();
                $table->unique('user_id');
            });
        }
    }
};
