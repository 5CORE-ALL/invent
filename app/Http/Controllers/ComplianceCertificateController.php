<?php

namespace App\Http\Controllers;

use App\Models\ComplianceCertificate;
use App\Models\ComplianceCertificateHistory;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ChannelMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ComplianceCertificateController extends Controller
{
    /**
     * Resolve an image URL for a product_master row.
     * Checks main_image, image1, and Values JSON (image_path / image / Image keys).
     */
    private function resolveProductImage($product): ?string
    {
        if (!$product) return null;

        $candidates = [];

        // Direct columns
        if (!empty($product->main_image)) $candidates[] = $product->main_image;
        if (!empty($product->image1)) $candidates[] = $product->image1;

        // Values JSON
        $values = $product->Values ?? null;
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        }
        if (is_array($values)) {
            foreach (['image_path', 'image', 'Image', 'IMG', 'main_image'] as $key) {
                if (!empty($values[$key])) {
                    $candidates[] = $values[$key];
                    break;
                }
            }
        }

        foreach ($candidates as $img) {
            $img = trim((string) $img);
            if ($img === '') continue;
            // Already absolute URL or data URL
            if (preg_match('/^(https?:)?\/\//i', $img) || str_starts_with($img, 'data:')) {
                return $img;
            }
            // Relative path - prefix with /
            return '/' . ltrim($img, '/');
        }

        return null;
    }

    /**
     * Convert array (from multiselect) or string into comma-separated string for DB storage.
     */
    private function formatMultiSelectValue($value): string
    {
        if (is_null($value) || $value === '') {
            return '';
        }
        if (is_array($value)) {
            return implode(',', array_filter(array_map('trim', $value)));
        }
        return (string) $value;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get active channels for dropdown
        $channels = ChannelMaster::where('status', 'Active')
            ->orderBy('channel')
            ->pluck('channel')
            ->toArray();
            
        return view('compliance-certificates.index', compact('channels'));
    }

    /**
     * Get channel names for table columns
     */
    public function getChannels()
    {
        $channels = ChannelMaster::where('status', 'Active')
            ->orderBy('channel')
            ->pluck('channel')
            ->toArray();
            
        return response()->json($channels);
    }

    /**
     * Get all certificates data (AJAX) - merged with product_master SKUs and inventory.
     */
    public function getData()
    {
        // ALWAYS load all SKUs from product_master so users can fill compliance for any SKU
        // Include image columns so we can display thumbnails in the table
        $productMasterRows = ProductMaster::select('sku', 'main_image', 'image1', 'Values')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('sku')
            ->get()
            ->keyBy('sku');
        $skus = $productMasterRows->pluck('sku')->toArray();
        
        // Get existing certificate records keyed by SKU for quick lookup
        $existingCerts = ComplianceCertificate::all()->keyBy('sku');
        
        // Get Shopify inventory data
        $shopifyData = ShopifySku::mapByProductSkus($skus);
        
        // Get all active channel names from channel_master
        $channels = ChannelMaster::where('status', 'Active')
            ->orderBy('channel')
            ->pluck('channel')
            ->toArray();
        
        // Build result array - merge SKUs from product_master with certificate data
        $result = [];
        
        foreach ($productMasterRows as $sku => $product) {
            // Get inventory from Shopify
            $shopifyRow = $shopifyData->get($sku);
            $inv = $shopifyRow ? ($shopifyRow->inv ?? 0) : 0;

            // Resolve product image (prefer Shopify image_src, fallback to product_master)
            $image = ($shopifyRow && !empty($shopifyRow->image_src))
                ? $shopifyRow->image_src
                : $this->resolveProductImage($product);
            
            // Check if certificate record exists for this SKU
            if (isset($existingCerts[$sku])) {
                $cert = $existingCerts[$sku];
                
                // Process files
                $files = [];
                if ($cert->certificate_files) {
                    $fileNames = explode(',', $cert->certificate_files);
                    foreach ($fileNames as $fileName) {
                        $files[] = [
                            'name' => basename($fileName),
                            'url' => Storage::url('certificates/' . basename($fileName))
                        ];
                    }
                }
                
                // certificate_available may be array (JSON cast) or string - normalize for frontend
                $certAvail = $cert->certificate_available;
                if (is_array($certAvail)) {
                    $certAvail = implode(',', $certAvail);
                }

                $result[] = [
                    'id' => $cert->id,
                    'sku' => $cert->sku,
                    'image' => $image,
                    'inv' => $inv,
                    'fcc' => $cert->fcc ? ($cert->fcc) : '',
                    'gcc' => $cert->gcc ? ($cert->gcc) : '',
                    'ul' => $cert->ul ? ($cert->ul) : '',
                    'battery' => $cert->battery ? ($cert->battery) : '',
                    'certificate_available' => $certAvail,
                    'certificate_files' => $cert->certificate_files,
                    'status' => $cert->status,
                    'updated_by' => $cert->updated_by,
                    'updated_at' => $cert->updated_at,
                    'files_array' => $files,
                    'updated_info' => $cert->updated_by ? 
                        $cert->updated_by . ' - ' . $cert->updated_at->format('Y-m-d H:i') : ''
                ];
            } else {
                // Create empty record for SKUs without certificate data
                $result[] = [
                    'id' => 'new_' . $sku,
                    'sku' => $sku,
                    'image' => $image,
                    'inv' => $inv,
                    'fcc' => '',
                    'gcc' => '',
                    'ul' => '',
                    'battery' => '',
                    'certificate_available' => null,
                    'certificate_files' => '',
                    'status' => 'Select',
                    'updated_by' => '',
                    'updated_at' => null,
                    'files_array' => [],
                    'updated_info' => ''
                ];
            }
        }
        
        return response()->json(['data' => $result, 'channels' => $channels]);
    }

    /**
     * Get SKU list with inventory (AJAX).
     */
    public function getSkuList(Request $request)
    {
        $search = $request->get('search', '');
        
        $query = ProductMaster::select('sku')
            ->whereNotNull('sku')
            ->where('sku', '!=', '');
        
        if ($search) {
            $query->where('sku', 'LIKE', "%{$search}%");
        }
        
        $products = $query->orderBy('sku')
            ->limit(100)
            ->get();
        
        $skus = $products->pluck('sku')->toArray();
        $shopifyData = ShopifySku::mapByProductSkus($skus);
        
        $result = [];
        foreach ($products as $product) {
            $shopifyRow = $shopifyData->get($product->sku);
            $result[] = [
                'sku' => $product->sku,
                'inv' => $shopifyRow ? ($shopifyRow->inv ?? 0) : 0
            ];
        }
        
        return response()->json($result);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return $this->saveCertificate($request);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        return $this->saveCertificate($request);
    }

    /**
     * Save (create or update) a certificate using updateOrCreate by SKU.
     */
    private function saveCertificate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sku' => 'required|string|max:255',
            'inv' => 'nullable|integer',
            'status' => 'nullable|string',
            'certificate_files.*' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed: ' . $validator->errors()->first()
            ], 422);
        }

        $userName = Auth::user()->name ?? 'System';
        $sku = $request->sku;

        // Snapshot the existing record (for change detection / history)
        $existing = ComplianceCertificate::where('sku', $sku)->first();
        $beforeSnapshot = $existing ? $existing->only(['fcc','gcc','ul','battery','certificate_available','certificate_files','status']) : [];

        $data = [
            'inv' => $request->input('inv'),
            'fcc' => $this->formatMultiSelectValue($request->input('fcc')),
            'gcc' => $this->formatMultiSelectValue($request->input('gcc')),
            'ul' => $this->formatMultiSelectValue($request->input('ul')),
            'battery' => $this->formatMultiSelectValue($request->input('battery')),
            'status' => $request->input('status'),
            'updated_by' => $userName,
        ];

        // Handle certificate_available - store as JSON array (model auto-casts)
        if ($request->has('certificate_available')) {
            $val = $request->input('certificate_available');
            if (is_array($val)) {
                $data['certificate_available'] = array_values(array_filter(array_map('trim', $val)));
            } elseif (is_string($val) && $val !== '') {
                $data['certificate_available'] = array_values(array_filter(array_map('trim', explode(',', $val))));
            } else {
                $data['certificate_available'] = null;
            }
        }

        // Track uploaded original file names for history
        $originalUploadedNames = [];

        // Handle file uploads (append to existing instead of replacing)
        if ($request->hasFile('certificate_files')) {
            $newFiles = [];
            foreach ($request->file('certificate_files') as $file) {
                $original = $file->getClientOriginalName();
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('public/certificates', $filename);
                $newFiles[] = $filename;
                $originalUploadedNames[] = $original;
            }

            // Append to existing files, keep history of all uploads for this SKU
            $existingFiles = $existing && $existing->certificate_files ? $existing->certificate_files : '';
            $combined = trim($existingFiles, ',');
            if ($combined !== '' && !empty($newFiles)) {
                $combined .= ',' . implode(',', $newFiles);
            } elseif (!empty($newFiles)) {
                $combined = implode(',', $newFiles);
            }
            $data['certificate_files'] = $combined;
        }

        $certificate = ComplianceCertificate::updateOrCreate(
            ['sku' => $sku],
            $data
        );

        // Build human-readable description of what changed
        $afterSnapshot = $certificate->only(['fcc','gcc','ul','battery','certificate_available','certificate_files','status']);
        $changedFields = [];
        foreach ($afterSnapshot as $key => $afterVal) {
            $beforeVal = $beforeSnapshot[$key] ?? null;
            // Normalize for comparison
            $beforeStr = is_array($beforeVal) ? implode(',', $beforeVal) : (string) ($beforeVal ?? '');
            $afterStr = is_array($afterVal) ? implode(',', $afterVal) : (string) ($afterVal ?? '');
            if ($beforeStr !== $afterStr) {
                $changedFields[$key] = ['from' => $beforeVal, 'to' => $afterVal];
            }
        }

        $action = !empty($originalUploadedNames) ? 'uploaded' : ($existing ? 'updated' : 'created');
        $description = $this->buildHistoryDescription($action, $changedFields, $originalUploadedNames);

        // Only log if something actually changed
        if (!empty($changedFields) || !empty($originalUploadedNames)) {
            ComplianceCertificateHistory::create([
                'sku' => $sku,
                'certificate_id' => $certificate->id,
                'action' => $action,
                'description' => $description,
                'changes' => $changedFields ?: null,
                'files_uploaded' => !empty($originalUploadedNames) ? implode(',', $originalUploadedNames) : null,
                'updated_by' => $userName,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Certificate saved successfully',
            'data' => $certificate
        ]);
    }

    /**
     * Build a human-readable description of what changed.
     */
    private function buildHistoryDescription(string $action, array $changedFields, array $uploadedNames): string
    {
        $parts = [];

        if (!empty($uploadedNames)) {
            $parts[] = 'Uploaded ' . count($uploadedNames) . ' file(s): ' . implode(', ', $uploadedNames);
        }

        $labels = [
            'fcc' => 'FCC',
            'gcc' => 'GCC',
            'ul' => 'UL',
            'battery' => 'Battery',
            'certificate_available' => 'Certificate Avl',
            'status' => 'Status',
            'certificate_files' => 'Files',
        ];

        foreach ($changedFields as $field => $diff) {
            if ($field === 'certificate_files') {
                continue; // covered by uploadedNames above
            }
            $label = $labels[$field] ?? $field;
            $to = is_array($diff['to']) ? implode(', ', $diff['to']) : ($diff['to'] ?? '');
            $parts[] = "Changed {$label} to: " . ($to !== '' ? $to : '(empty)');
        }

        return $parts ? implode('; ', $parts) : ucfirst($action);
    }

    /**
     * Get history records for a specific SKU (passed via query param to support special chars).
     */
    public function getHistory(Request $request)
    {
        $sku = $request->query('sku', '');
        if ($sku === '') {
            return response()->json(['error' => 'SKU is required'], 422);
        }

        $history = ComplianceCertificateHistory::where('sku', $sku)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($h) {
                return [
                    'id' => $h->id,
                    'sku' => $h->sku,
                    'action' => $h->action,
                    'description' => $h->description,
                    'files_uploaded' => $h->files_uploaded,
                    'updated_by' => $h->updated_by,
                    'created_at' => $h->created_at?->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json($history);
    }

    /**
     * Get ALL history records across all SKUs (latest first).
     */
    public function getAllHistory(Request $request)
    {
        $limit = (int) $request->input('limit', 500);
        $history = ComplianceCertificateHistory::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($h) {
                return [
                    'id' => $h->id,
                    'sku' => $h->sku,
                    'action' => $h->action,
                    'description' => $h->description,
                    'files_uploaded' => $h->files_uploaded,
                    'updated_by' => $h->updated_by,
                    'created_at' => $h->created_at?->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json($history);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $certificate = ComplianceCertificate::findOrFail($id);

        // Delete associated files
        if ($certificate->certificate_files) {
            $files = explode(',', $certificate->certificate_files);
            foreach ($files as $file) {
                Storage::delete('public/certificates/' . $file);
            }
        }

        $sku = $certificate->sku;
        $certificate->delete();

        // Log delete in history
        ComplianceCertificateHistory::create([
            'sku' => $sku,
            'certificate_id' => null,
            'action' => 'deleted',
            'description' => 'Certificate record deleted',
            'updated_by' => Auth::user()->name ?? 'System',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Certificate deleted successfully'
        ]);
    }

    /**
     * Bulk update multiple SKUs at once.
     * Only fields with values are updated; empty fields are skipped.
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'skus' => 'required|array|min:1',
            'skus.*' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide at least one SKU'
            ], 422);
        }

        $skus = array_values(array_unique(array_filter(array_map('trim', $request->input('skus', [])))));
        $userName = Auth::user()->name ?? 'System';

        // Build the data to update - only include fields that have values
        $bulkData = [];
        $bulkSummary = [];

        foreach (['fcc', 'gcc', 'ul', 'battery'] as $field) {
            $val = $request->input($field);
            if (is_array($val) && !empty($val)) {
                $val = array_values(array_filter(array_map('trim', $val)));
                if (!empty($val)) {
                    $bulkData[$field] = implode(',', $val);
                    $bulkSummary[strtoupper($field)] = $bulkData[$field];
                }
            }
        }

        $certAvl = $request->input('certificate_available');
        if (is_array($certAvl) && !empty($certAvl)) {
            $cleaned = array_values(array_filter(array_map('trim', $certAvl)));
            if (!empty($cleaned)) {
                $bulkData['certificate_available'] = $cleaned; // model auto-casts to JSON
                $bulkSummary['Cert Avl'] = implode(', ', $cleaned);
            }
        }

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $bulkData['status'] = $status;
            $bulkSummary['Status'] = $status;
        }

        if (empty($bulkData)) {
            return response()->json([
                'success' => false,
                'message' => 'No fields provided to update'
            ], 422);
        }

        $bulkData['updated_by'] = $userName;

        // Build a description of what changed for history log
        $description = 'Bulk update: ';
        $descParts = [];
        foreach ($bulkSummary as $label => $value) {
            $descParts[] = "{$label} = {$value}";
        }
        $description .= implode('; ', $descParts);

        $updatedCount = 0;
        foreach ($skus as $sku) {
            $certificate = ComplianceCertificate::updateOrCreate(
                ['sku' => $sku],
                $bulkData
            );

            ComplianceCertificateHistory::create([
                'sku' => $sku,
                'certificate_id' => $certificate->id,
                'action' => 'updated',
                'description' => $description,
                'changes' => $bulkSummary,
                'updated_by' => $userName,
            ]);

            $updatedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk update completed',
            'updated' => $updatedCount,
            'skus' => $skus,
        ]);
    }

    /**
     * Delete a single file from a certificate's file list.
     */
    public function deleteFile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sku' => 'required|string',
            'filename' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $validator->errors()->first()
            ], 422);
        }

        $sku = $request->sku;
        $filename = basename($request->filename); // sanitize - prevent directory traversal

        $certificate = ComplianceCertificate::where('sku', $sku)->first();
        if (!$certificate) {
            return response()->json([
                'success' => false,
                'message' => 'Certificate record not found for this SKU'
            ], 404);
        }

        $files = $certificate->certificate_files ? explode(',', $certificate->certificate_files) : [];
        $files = array_values(array_filter($files, fn($f) => trim($f) !== ''));

        if (!in_array($filename, $files)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found in this certificate'
            ], 404);
        }

        // Remove from storage
        Storage::delete('public/certificates/' . $filename);

        // Remove from list and update record
        $remaining = array_values(array_filter($files, fn($f) => $f !== $filename));
        $certificate->certificate_files = !empty($remaining) ? implode(',', $remaining) : null;
        $certificate->updated_by = Auth::user()->name ?? 'System';
        $certificate->save();

        // Log in history
        ComplianceCertificateHistory::create([
            'sku' => $sku,
            'certificate_id' => $certificate->id,
            'action' => 'deleted',
            'description' => 'Deleted file: ' . $filename,
            'files_uploaded' => $filename,
            'updated_by' => Auth::user()->name ?? 'System',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File deleted successfully',
            'remaining_files' => $remaining
        ]);
    }

    /**
     * Upload files via AJAX
     */
    public function uploadFiles(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files.*' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $uploadedFiles = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('public/certificates', $filename);
                $uploadedFiles[] = [
                    'name' => $filename,
                    'url' => Storage::url('certificates/' . $filename)
                ];
            }
        }

        return response()->json([
            'success' => true,
            'files' => $uploadedFiles
        ]);
    }
}
