<?php

use App\Models\AmazonAdsCampaignAudit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        AmazonAdsCampaignAudit::ensureTable();
    }

    public function down(): void
    {
        Schema::dropIfExists(AmazonAdsCampaignAudit::TABLE);
    }
};
