<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\ShopifyHtmlTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyHtmlTemplateController extends Controller
{
    /**
     * GET /product-description/shopify-templates — list built-in + current user's templates.
     */
    public function index(Request $request)
    {
        $marketplace = strtolower(trim((string) $request->query('marketplace', 'shopify_main')));
        if (! in_array($marketplace, ['shopify_main', 'shopify_pls', 'all'], true)) {
            $marketplace = 'shopify_main';
        }

        $userId = auth()->id();

        $system = ShopifyHtmlTemplate::query()
            ->where('is_system', true)
            ->where(function ($q) use ($marketplace) {
                $q->where('marketplace', 'all')->orWhere('marketplace', $marketplace);
            })
            ->orderBy('template_name')
            ->get(['id', 'template_name', 'marketplace', 'is_system', 'sku', 'created_at']);

        $mine = collect();
        if ($userId) {
            $mine = ShopifyHtmlTemplate::query()
                ->where('is_system', false)
                ->where('user_id', $userId)
                ->where(function ($q) use ($marketplace) {
                    $q->where('marketplace', 'all')->orWhere('marketplace', $marketplace);
                })
                ->orderBy('template_name')
                ->get(['id', 'template_name', 'marketplace', 'is_system', 'sku', 'created_at']);
        }

        $templates = $system->concat($mine)->values();

        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * GET /product-description/shopify-templates/{id} — full HTML for one template.
     */
    public function show(int $id)
    {
        $userId = auth()->id();
        $row = ShopifyHtmlTemplate::query()->find($id);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Template not found.'], 404);
        }
        if ($row->is_system) {
            return response()->json([
                'success' => true,
                'template' => [
                    'id' => $row->id,
                    'template_name' => $row->template_name,
                    'html_content' => $row->html_content,
                    'is_system' => true,
                    'marketplace' => $row->marketplace,
                ],
            ]);
        }
        if (! $userId || (int) $row->user_id !== (int) $userId) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'success' => true,
            'template' => [
                'id' => $row->id,
                'template_name' => $row->template_name,
                'html_content' => $row->html_content,
                'is_system' => false,
                'marketplace' => $row->marketplace,
                'sku' => $row->sku,
            ],
        ]);
    }

    /**
     * POST /product-description/shopify-templates/save — create or update user template.
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:shopify_html_templates,id',
            'template_name' => 'required|string|max:255',
            'html_content' => 'required|string|max:500000',
            'marketplace' => 'required|string|in:shopify_main,shopify_pls,all',
            'sku' => 'nullable|string|max:191',
        ]);

        $userId = auth()->id();
        if (! $userId) {
            return response()->json(['success' => false, 'message' => 'You must be logged in to save templates.'], 403);
        }

        $name = trim($validated['template_name']);
        $html = $validated['html_content'];
        $mp = $validated['marketplace'];
        $sku = isset($validated['sku']) ? trim((string) $validated['sku']) : null;
        if ($sku === '') {
            $sku = null;
        }

        try {
            if (! empty($validated['id'])) {
                $row = ShopifyHtmlTemplate::query()->find((int) $validated['id']);
                if (! $row || $row->is_system) {
                    return response()->json(['success' => false, 'message' => 'Cannot edit this template.'], 422);
                }
                if ((int) $row->user_id !== (int) $userId) {
                    return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
                }
                $row->update([
                    'template_name' => $name,
                    'html_content' => $html,
                    'marketplace' => $mp,
                    'sku' => $sku,
                ]);
                Log::info('ShopifyHtmlTemplate: updated', ['id' => $row->id, 'user_id' => $userId]);

                return response()->json(['success' => true, 'message' => 'Template updated.', 'id' => $row->id]);
            }

            $row = ShopifyHtmlTemplate::query()->create([
                'user_id' => $userId,
                'sku' => $sku,
                'marketplace' => $mp,
                'template_name' => $name,
                'html_content' => $html,
                'is_system' => false,
            ]);
            Log::info('ShopifyHtmlTemplate: created', ['id' => $row->id, 'user_id' => $userId]);

            return response()->json(['success' => true, 'message' => 'Template saved.', 'id' => $row->id]);
        } catch (\Throwable $e) {
            Log::error('ShopifyHtmlTemplate: save failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /product-description/shopify-templates/{id}
     */
    public function destroy(int $id)
    {
        $userId = auth()->id();
        if (! $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $row = ShopifyHtmlTemplate::query()->find($id);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }
        if ($row->is_system) {
            return response()->json(['success' => false, 'message' => 'Built-in templates cannot be deleted.'], 422);
        }
        if ((int) $row->user_id !== (int) $userId) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $row->delete();

        return response()->json(['success' => true, 'message' => 'Template deleted.']);
    }
}
