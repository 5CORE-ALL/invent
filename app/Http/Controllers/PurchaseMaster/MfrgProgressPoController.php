<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\MfrgProgress;
use App\Models\MfrgProgressPo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MfrgProgressPoController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'mfrg_progress_id' => 'required|integer',
            'sku' => 'nullable|string|max:128',
            'po_number' => 'nullable|string|max:100',
        ]);

        $mip = MfrgProgress::query()->find($validated['mfrg_progress_id']);
        if (! $mip) {
            return response()->json([
                'success' => false,
                'message' => 'MIP row not found.',
            ], 404);
        }

        $stored = $this->normalizePoNumber($validated['po_number'] ?? '');

        if ($stored === '') {
            MfrgProgressPo::query()->where('mfrg_progress_id', $mip->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cleared.',
                'po_number' => '',
            ]);
        }

        $row = MfrgProgressPo::query()->updateOrCreate(
            ['mfrg_progress_id' => $mip->id],
            [
                'sku' => $mip->sku,
                'po_number' => $stored,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Saved.',
            'po_number' => $row->po_number,
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.mfrg_progress_id' => 'required|integer',
            'po_number' => 'nullable|string|max:100',
        ]);

        $stored = $this->normalizePoNumber($validated['po_number'] ?? '');
        $ids = collect($validated['items'])->pluck('mfrg_progress_id')->map(fn ($id) => (int) $id)->unique()->values();
        $mipRows = MfrgProgress::query()->whereIn('id', $ids)->get()->keyBy('id');

        $updated = 0;

        DB::transaction(function () use ($ids, $mipRows, $stored, &$updated) {
            foreach ($ids as $mipId) {
                $mip = $mipRows->get($mipId);
                if (! $mip) {
                    continue;
                }

                if ($stored === '') {
                    MfrgProgressPo::query()->where('mfrg_progress_id', $mip->id)->delete();
                    $updated++;

                    continue;
                }

                MfrgProgressPo::query()->updateOrCreate(
                    ['mfrg_progress_id' => $mip->id],
                    ['sku' => $mip->sku, 'po_number' => $stored]
                );
                $updated++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Bulk PO update complete.',
            'updated_count' => $updated,
            'po_number' => $stored,
        ]);
    }

    private function normalizePoNumber(?string $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? '' : mb_substr($text, 0, 100);
    }
}
