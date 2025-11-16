<?php

namespace App\Console\Commands;

use App\Http\Controllers\ApiController;
use App\Models\FaireProductSheet;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchFaireData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-faire-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    // protected $clientId;
    // protected $clientSecret;
    // protected $redirectUrl;

    // public function __construct()
    // {
    //     parent::__construct();
    //     $this->clientId     = env('FAIRE_APP_ID');
    //     $this->clientSecret = env('FAIRE_APP_SECRET');
    //     $this->redirectUrl  = env('FAIRE_REDIRECT_URL');
    // }

    /**
     * Execute the console command.
     */
    // public function handle()
    // {
    //     $this->info("Fetching Faire orders…");

    //     $today = Carbon::today();
    //     $l30Start = $today->copy()->subDays(30);
    //     $l60Start = $today->copy()->subDays(60);

    //     $l30End = $today;
    //     $l60End = $l30Start->copy()->subDay();

    //     // Ask user for auth code (one-time per run)
    //     $authorizationCode = $this->ask('Enter Faire Authorization Code');

    //     // Step 1: Get Access Token
    //     $tokenResponse = Http::post('https://www.faire.com/api/external-api-oauth2/token', [
    //         'applicationId'     => $this->clientId,
    //         'applicationSecret' => $this->clientSecret,
    //         'redirectUrl'       => $this->redirectUrl,
    //         'scope'             => ['READ_ORDERS'],
    //         'grantType'         => 'AUTHORIZATION_CODE',
    //         'authorizationCode' => $authorizationCode,
    //     ]);

    //     if (!$tokenResponse->successful()) {
    //         $this->error("Failed to get access token");
    //         $this->error($tokenResponse->body());
    //         return 1;
    //     }

    //     $accessToken = $tokenResponse->json('access_token');
    //     if (!$accessToken) {
    //         $this->error("No access token in response");
    //         return 1;
    //     }

    //     $this->info("Access token retrieved.");

    //     // Step 2: Fetch orders from Faire
    //     $endpoint = "https://www.faire.com/external-api/v2/orders?page=1&limit=50";
    //     $ordersCollection = collect();

    //     while ($endpoint) {
    //         $response = Http::withHeaders([
    //             'X-FAIRE-OAUTH-ACCESS-TOKEN' => $accessToken,
    //             'Accept' => 'application/json',
    //         ])->get($endpoint);

    //         if (!$response->successful()) {
    //             $this->error("Failed to fetch orders from Faire");
    //             $this->error($response->body());
    //             return 1;
    //         }

    //         $data = $response->json();
    //         $orders = collect($data['orders'] ?? []);
    //         $ordersCollection = $ordersCollection->merge($orders);

    //         $this->info("Fetched " . $orders->count() . " orders (total: " . $ordersCollection->count() . ")");

    //         // Pagination (check next page)
    //         if (!empty($data['next'])) {
    //             $endpoint = $data['next'];
    //         } else {
    //             $endpoint = null;
    //         }
    //     }

    //     // Step 3: Group by SKU
    //     $grouped = $ordersCollection->flatMap(function ($order) {
    //         return collect($order['items'] ?? [])->map(function ($item) use ($order) {
    //             return [
    //                 'sku'        => $item['sku'] ?? null,
    //                 'created_at' => $order['created_at'],
    //                 'price'      => $item['price']['amount_cents'] / 100 ?? 0, // Faire gives cents
    //             ];
    //         });
    //     })->filter(fn($i) => !empty($i['sku']))->groupBy('sku');

    //     // Step 4: Save L30 / L60 / Price into DB
    //     foreach ($grouped as $sku => $items) {
    //         $l30 = $items->filter(fn($i) => Carbon::parse($i['created_at'])->between($l30Start, $l30End))->count();
    //         $l60 = $items->filter(fn($i) => Carbon::parse($i['created_at'])->between($l60Start, $l60End))->count();
    //         $price = $items->last()['price'] ?? 0;

    //         FaireProductSheet::updateOrCreate(
    //             ['sku' => $sku],
    //             [
    //                 'l30'   => $l30,
    //                 'l60'   => $l60,
    //                 'price' => $price,
    //             ]
    //         );
    //     }

    //     $this->info("Faire L30/L60/Price updated successfully.");
    //     return 0;
    // }


    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        parent::__construct();
        $this->apiController = $apiController;
    }

    public function handle()
    {
        // Call your controller method
        $response = $this->apiController->fetchDataFromFairMasterGoogleSheet();
        $payload = $response->getData(true); // convert JSON response to array

        if (!isset($payload['data'])) {
            $this->error("No data returned from controller.");
            return 1;
        }

        foreach ($payload['data'] as $item) {
            FaireProductSheet::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'f_l30' => !empty($item['f_l30']) ? (int)$item['f_l30'] : 0,
                    'f_l60' => !empty($item['f_l60']) ? (int)$item['f_l60'] : 0,
                    'views' => !empty($item['views']) ? (int)$item['views'] : 0,
                    'price' => !empty($item['price']) ? (float)$item['price'] : 0,
                ]
            );
        }

        $this->info("faire Sheet data synced successfully via controller.");
        return 0;
    }
}
