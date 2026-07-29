<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ApiController;
use App\Models\TemuProductSheet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class SyncTemuSheet extends Command
{
    protected $signature = 'sync:temu-sheet';
    protected $description = 'Sync Temu Product Sheet';

    protected $apiUrl;
    protected $apiKey;
    protected $password;

    public function __construct()
    {
        parent::__construct();
        $this->apiUrl    = "https://" . config('services.shopify_5core.domain') . "/admin/api/2024-10";
        $this->apiKey    = config('services.shopify_5core.api_key');
        $this->password  = config('services.shopify_5core.password');
    }


    public function handle()
    {
        $this->info("Temu sheet sync has been deprecated and removed (temu_pricing / temu_product_sheets dropped).");
        return 0;
    }
}
