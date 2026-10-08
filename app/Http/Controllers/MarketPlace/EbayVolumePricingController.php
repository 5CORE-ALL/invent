<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Services\Ebay1VolumePricingService;
use App\Support\EbayVolumePricingRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EbayVolumePricingController extends Controller
{
    public function index()
    {
        return view('market-places.ebay1_volume_pricing');
    }

    public function rules(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'rules' => $this->loadRules(),
        ]);
    }

    public function saveRules(Request $request): JsonResponse
    {
        $rules = $this->storeRules($request->all(), false);

        return response()->json([
            'success' => true,
            'message' => $rules['enabled'] ? 'Volume pricing rules are on.' : 'Volume pricing rules are off.',
            'rules' => $rules,
        ]);
    }

    public function push(Request $request, Ebay1VolumePricingService $service): JsonResponse
    {
        $payload = $request->all();
        $rules = $this->storeRules($payload['rules'] ?? $this->loadRules(), true);
        if (! $rules['enabled']) {
            return response()->json([
                'success' => false,
                'message' => 'Turn the volume pricing rules on before pushing to eBay.',
            ], 422);
        }

        $rows = $request->input('rows', []);
        if (! is_array($rows) || $rows === []) {
            return response()->json([
                'success' => false,
                'message' => 'No listings to push.',
            ], 422);
        }

        @set_time_limit(180);
        $result = $service->push($rules, array_slice($rows, 0, 5000));
        $rules['promotion_ids'] = $result['promotion_ids'];
        $this->persist($rules);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function storeRules(array $raw, bool $keepIdsOnly): array
    {
        $existing = $this->loadRules();
        $rules = EbayVolumePricingRule::merge($raw);
        $rules['promotion_ids'] = $existing['promotion_ids'] ?? [];
        if (! $keepIdsOnly) {
            $this->persist($rules);
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadRules(): array
    {
        if (! Schema::hasTable('ebay_sbid_rules')) {
            return EbayVolumePricingRule::defaults();
        }
        $row = DB::table('ebay_sbid_rules')->where('key', EbayVolumePricingRule::KEY)->first();
        $saved = null;
        if ($row && isset($row->rule)) {
            $decoded = is_array($row->rule) ? $row->rule : json_decode((string) $row->rule, true);
            $saved = is_array($decoded) ? $decoded : null;
        }

        return EbayVolumePricingRule::merge($saved);
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function persist(array $rules): void
    {
        if (! Schema::hasTable('ebay_sbid_rules')) {
            return;
        }
        $now = now();
        $values = [
            'rule' => json_encode($rules),
            'updated_at' => $now,
        ];
        $exists = DB::table('ebay_sbid_rules')->where('key', EbayVolumePricingRule::KEY)->exists();
        if (! $exists) {
            $values['created_at'] = $now;
        }
        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => EbayVolumePricingRule::KEY],
            $values
        );
    }
}
