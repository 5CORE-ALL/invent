<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PurchaseMaster\ContainerQuoteRequestController;
use App\Http\Controllers\CustomerCare\QcAndPackingController;
use App\Models\ProductMaster;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Spare / Parts / Packaging list. SKU, Parent and Supplier come from Product Master and Suppliers. */
class SparePartPackagingController extends Controller
{
    public function index()
    {
        return view('inventory-management.spare-part-packaging');
    }

    public function data(): JsonResponse
    {
        if (! Schema::hasTable('spare_part_packagings')) {
            return response()->json(['data' => [], 'message' => 'Run php artisan migrate first.']);
        }

        $tz = config('app.timezone');
        $rows = DB::table('spare_part_packagings')->orderByDesc('id')->get()->map(function ($r) use ($tz) {
            return [
                'id' => (int) $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'image_url' => $r->image_path ? Storage::disk('public')->url($r->image_path) : null,
                'moq' => $r->moq,
                'msl' => $r->msl,
                'qty_available' => $r->qty_available ?? null,
                'parent' => $r->parent,
                'sku' => $r->sku,
                'supplier' => $r->supplier,
                'created_by' => $r->created_by,
                'created_at' => $r->created_at ? Carbon::parse($r->created_at)->timezone($tz)->format('d-m-Y') : '',
            ];
        })->values();

        return response()->json(['data' => $rows]);
    }

    /** Dropdown lists for the Add + form (same sources as the other tabulators). */
    public function options(): JsonResponse
    {
        $products = ProductMaster::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('sku', 'NOT LIKE', 'PARENT %')
            ->orderBy('sku')
            ->get(['sku', 'parent']);

        $skus = [];
        $seen = [];
        $parents = [];
        foreach ($products as $p) {
            $sku = trim((string) $p->sku);
            $parent = trim((string) ($p->parent ?? ''));
            if ($sku === '' || isset($seen[mb_strtoupper($sku)])) {
                continue;
            }
            $seen[mb_strtoupper($sku)] = true;
            $skus[] = ['sku' => $sku, 'parent' => $parent];
            if ($parent !== '') {
                $parents[$parent] = true;
            }
        }
        $parents = array_keys($parents);
        sort($parents, SORT_NATURAL | SORT_FLAG_CASE);

        $suppliers = Supplier::distinctNamesForListPage()->values()->all();

        // Supplier per SKU (same lookup as QC / PKG), kept only when it matches the supplier list.
        $byUpper = [];
        foreach ($suppliers as $name) {
            $byUpper[mb_strtoupper(trim((string) $name))] = $name;
        }
        $skus = app(QcAndPackingController::class)->attachSuppliers($skus);
        foreach ($skus as $i => $row) {
            $skus[$i]['supplier'] = $byUpper[mb_strtoupper(trim((string) ($row['supplier'] ?? '')))] ?? '';
        }

        return response()->json([
            'skus' => $skus,
            'parents' => $parents,
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! Schema::hasTable('spare_part_packagings')) {
            return response()->json(['message' => 'Run php artisan migrate first.'], 500);
        }

        $fields = $this->validatedFields($request);
        if ($fields instanceof JsonResponse) {
            return $fields;
        }

        $path = $request->hasFile('image') ? $request->file('image')->store('spare_part_packaging', 'public') : null;

        $user = $request->user();
        $id = DB::table('spare_part_packagings')->insertGetId($fields + [
            'image_path' => $path,
            'created_by' => trim((string) ($user?->name ?? '')) ?: 'System',
            'created_by_user_id' => $user?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    /** Edit a row (POST because the form can carry a new image file). */
    public function update(Request $request, int $id): JsonResponse
    {
        $row = $this->findRow($id);
        if (! $row) {
            return response()->json(['message' => 'Row not found.'], 404);
        }

        $fields = $this->validatedFields($request);
        if ($fields instanceof JsonResponse) {
            return $fields;
        }

        $fields['image_path'] = $row->image_path;
        if ($request->hasFile('image')) {
            $fields['image_path'] = $request->file('image')->store('spare_part_packaging', 'public');
            $this->deleteImage($row->image_path);
        } elseif ($request->boolean('remove_image')) {
            $fields['image_path'] = null;
            $this->deleteImage($row->image_path);
        }
        $fields['updated_at'] = now();

        DB::table('spare_part_packagings')->where('id', $id)->update($fields);

        return response()->json(['success' => true]);
    }

    /** Add stock to Qty Available. */
    public function addQty(Request $request, int $id): JsonResponse
    {
        $row = $this->findRow($id);
        if (! $row) {
            return response()->json(['message' => 'Row not found.'], 404);
        }

        $validated = $request->validate(['qty' => 'required|integer|min:1|max:100000000']);
        $new = (int) ($row->qty_available ?? 0) + (int) $validated['qty'];

        DB::table('spare_part_packagings')->where('id', $id)->update(['qty_available' => $new, 'updated_at' => now()]);

        return response()->json(['success' => true, 'qty_available' => $new]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = $this->findRow($id);
        if (! $row) {
            return response()->json(['message' => 'Row not found.'], 404);
        }

        DB::table('spare_part_packagings')->where('id', $id)->delete();
        $this->deleteImage($row->image_path);

        return response()->json(['success' => true]);
    }

    /** Editable draft of the "CR" (Create Request) email for one row, addressed to its supplier. */
    public function crDraft(Request $request, int $id): JsonResponse
    {
        $row = $this->findRow($id);
        if (! $row) {
            return response()->json(['message' => 'Row not found.'], 404);
        }

        $supplierName = trim((string) ($row->supplier ?? ''));
        $emails = '';
        if ($supplierName !== '') {
            $raw = (string) Supplier::query()->whereRaw('TRIM(UPPER(name)) = ?', [mb_strtoupper($supplierName)])->value('email');
            $emails = implode(', ', $this->splitEmails($raw));
        }

        $sender = app(ContainerQuoteRequestController::class)->sender();
        $userName = trim((string) ($request->user()?->name ?? '')) ?: $sender['name'];

        $lines = [];
        $lines[] = 'Dear '.($supplierName !== '' ? $supplierName : 'Sir / Madam').',';
        $lines[] = '';
        $lines[] = 'Greetings from 5Core. We would like to request the following spare part / packaging / material:';
        $lines[] = '';
        $lines[] = 'Item: '.$row->name;
        if (trim((string) $row->description) !== '') {
            $lines[] = 'Description: '.trim((string) $row->description);
        }
        if (trim((string) $row->sku) !== '') {
            $lines[] = 'SKU: '.$row->sku;
        }
        if (trim((string) $row->parent) !== '') {
            $lines[] = 'Parent: '.$row->parent;
        }
        $lines[] = 'Quantity required: '.($row->moq ? (int) $row->moq.' (MOQ)' : '');
        $lines[] = '';
        $lines[] = 'Please share the price, availability and lead time at your earliest convenience.';
        $lines[] = '';
        $lines[] = 'Thank you.';
        $lines[] = '';
        $lines[] = 'Best regards,';
        $lines[] = $userName;
        $lines[] = '5Core';

        return response()->json([
            'to' => $emails,
            'from' => $sender['email'],
            'subject' => 'Request for '.$row->name.($row->sku ? ' ('.$row->sku.')' : ''),
            'body' => implode("\n", $lines),
            'supplier' => $supplierName,
        ]);
    }

    /** Sends the (edited) CR email from the app. */
    public function crSend(Request $request, int $id): JsonResponse
    {
        $row = $this->findRow($id);
        if (! $row) {
            return response()->json(['message' => 'Row not found.'], 404);
        }

        $validated = $request->validate([
            'to' => 'required|string|max:1000',
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:20000',
        ]);

        $to = $this->splitEmails($validated['to']);
        if ($to === []) {
            return response()->json(['message' => 'Enter a valid supplier email address.', 'errors' => ['to' => ['Enter a valid supplier email address.']]], 422);
        }

        $sender = app(ContainerQuoteRequestController::class)->sender();
        $html = nl2br(e($validated['body']));

        try {
            Mail::mailer($sender['mailer'])->html($html, function ($m) use ($sender, $to, $validated, $request) {
                $m->from($sender['email'], $sender['name'])->subject($validated['subject']);
                $replyTo = trim((string) ($request->user()?->email ?? ''));
                if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                    $m->replyTo($replyTo, $request->user()?->name);
                }
                foreach ($to as $addr) {
                    $m->to($addr);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Spare/packaging CR email failed: '.$e->getMessage());

            return response()->json(['message' => 'Could not send the email: '.$e->getMessage()], 500);
        }

        Log::info('Spare/packaging CR email sent', ['row_id' => $id, 'to' => $to, 'from' => $sender['email'], 'by' => $request->user()?->id]);

        return response()->json(['success' => true, 'message' => 'Email sent to '.implode(', ', $to).'.']);
    }

    /** @return list<string> */
    private function splitEmails(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $part) {
            $part = trim($part, " \t\n\r\0\x0B<>");
            if ($part !== '' && filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $out[strtolower($part)] = $part;
            }
        }

        return array_values($out);
    }

    private function findRow(int $id): ?object
    {
        if (! Schema::hasTable('spare_part_packagings')) {
            return null;
        }

        return DB::table('spare_part_packagings')->where('id', $id)->first();
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Validates the form and checks SKU / Parent / Supplier against the existing lists.
     *
     * @return array<string, mixed>|JsonResponse
     */
    private function validatedFields(Request $request): array|JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'image' => 'nullable|image|max:5120',
            'moq' => 'nullable|integer|min:0|max:100000000',
            'msl' => 'nullable|integer|min:0|max:100000000',
            'qty_available' => 'nullable|integer|min:-100000000|max:100000000',
            'parent' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:128',
            'supplier' => 'nullable|string|max:255',
        ]);

        $sku = trim((string) ($validated['sku'] ?? ''));
        $parent = trim((string) ($validated['parent'] ?? ''));
        $supplier = trim((string) ($validated['supplier'] ?? ''));

        if ($sku !== '') {
            $product = ProductMaster::query()->whereRaw('TRIM(UPPER(sku)) = ?', [mb_strtoupper($sku)])->first(['sku', 'parent']);
            if (! $product) {
                return response()->json(['message' => 'Pick a SKU from the list.', 'errors' => ['sku' => ['Pick a SKU from the list.']]], 422);
            }
            $sku = trim((string) $product->sku);
            if ($parent === '') {
                $parent = trim((string) ($product->parent ?? ''));
            }
        }
        if ($parent !== '' && ! ProductMaster::query()->whereRaw('TRIM(UPPER(parent)) = ?', [mb_strtoupper($parent)])->exists()) {
            return response()->json(['message' => 'Pick a Parent from the list.', 'errors' => ['parent' => ['Pick a Parent from the list.']]], 422);
        }
        if ($supplier !== '') {
            $found = Supplier::query()->whereRaw('TRIM(UPPER(name)) = ?', [mb_strtoupper($supplier)])->value('name');
            if ($found === null) {
                return response()->json(['message' => 'Pick a Supplier from the list.', 'errors' => ['supplier' => ['Pick a Supplier from the list.']]], 422);
            }
            $supplier = trim((string) $found);
        }

        return [
            'name' => trim($validated['name']),
            'description' => isset($validated['description']) && trim($validated['description']) !== '' ? trim($validated['description']) : null,
            'moq' => $validated['moq'] ?? null,
            'msl' => $validated['msl'] ?? null,
            'qty_available' => $validated['qty_available'] ?? null,
            'parent' => $parent !== '' ? $parent : null,
            'sku' => $sku !== '' ? $sku : null,
            'supplier' => $supplier !== '' ? $supplier : null,
        ];
    }
}
