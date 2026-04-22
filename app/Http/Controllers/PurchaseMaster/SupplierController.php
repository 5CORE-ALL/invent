<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\SupplierRating;
use App\Models\SupplierRemarkHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SupplierController extends Controller
{
    function supplierList(Request $request)
    {
        $query = Supplier::query();

        // Apply category filter
        if ($request->filled('category')) {
            $categoryName = $request->get('category');
            $category = Category::where('name', $categoryName)->first();
            if ($category) {
                $query->where('category_id', 'LIKE', '%' . $category->id . '%');
            }
        }

        // Apply type filter
        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                  ->orWhere('company', 'LIKE', '%' . $search . '%')
                  ->orWhere('email', 'LIKE', '%' . $search . '%')
                  ->orWhere('phone', 'LIKE', '%' . $search . '%');
            });
        }

        // Get total count before pagination (for filtered results)
        $filteredCount = $query->count();
        
        // Get total count of all suppliers (unfiltered)
        $totalCount = Supplier::count();
        
        $suppliers = $query->with(['ratings', 'latestRemark'])->paginate(20)->appends($request->query());
        $categories = Category::orderBy('name')->get();
        
        // If AJAX request, return JSON
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'html' => view('purchase-master.supplier.partials.rows', compact('suppliers', 'categories'))->render(),
                'pagination' => (string) $suppliers->onEachSide(1)->links('pagination::bootstrap-5'),
                'filteredCount' => $filteredCount,
                'totalCount' => $totalCount,
            ]);
        }
        
        return view('purchase-master.supplier.suppliers' , compact('suppliers', 'categories', 'filteredCount', 'totalCount'));
    }

    /**
     * Return suppliers list as JSON for dropdowns (e.g. forecast analysis).
     */
    public function getSuppliersJson(Request $request)
    {
        $suppliers = Supplier::distinctNameRowsForDropdownJson()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]);
        return response()->json(['suppliers' => $suppliers]);
    }

    /**
     * Return categories as JSON for add-supplier form (e.g. on forecast page).
     */
    public function getCategoriesJson(Request $request)
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        return response()->json(['categories' => $categories]);
    }

    public function postSupplier(Request $request)
    {
        $data = $request->except('_token');

        $rules = [
            'type'         => 'required|string',
            'category_id'  => 'required|array',
            'category_id.*'=> 'integer',
            'name'         => 'required|string',
            'parent'       => 'nullable|string',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $inputs = $request->all();

        try {
            if (!empty($inputs['supplier_id'])) {
                $supplier = Supplier::findOrFail($inputs['supplier_id']);
            } else {
                $supplier = new Supplier;
            }

            $supplier->type         = trim($inputs['type']);
            $supplier->category_id  = !empty($inputs['category_id']) && is_array($inputs['category_id']) 
                ? implode(',', array_filter($inputs['category_id'])) 
                : null;
            $supplier->name         = trim($inputs['name']);
            $supplier->company      = !empty($inputs['company']) ? trim($inputs['company']) : null;
            $supplier->parent       = !empty($inputs['parent']) ? trim($inputs['parent']) : null;
            $supplier->country_code = !empty($inputs['country_code']) ? trim($inputs['country_code']) : null;
            $supplier->phone        = !empty($inputs['phone']) ? trim($inputs['phone']) : null;
            $supplier->city         = !empty($inputs['city']) ? trim($inputs['city']) : null;
            $supplier->zone         = !empty($inputs['zone']) ? trim($inputs['zone']) : null;
            $supplier->email        = !empty($inputs['email']) ? trim($inputs['email']) : null;
            $supplier->whatsapp     = !empty($inputs['whatsapp']) ? trim($inputs['whatsapp']) : null;
            $supplier->wechat       = !empty($inputs['wechat']) ? trim($inputs['wechat']) : null;
            $supplier->alibaba      = !empty($inputs['alibaba']) ? trim($inputs['alibaba']) : null;
            $supplier->website      = !empty($inputs['website']) ? trim($inputs['website']) : null;
            $supplier->others       = !empty($inputs['others']) ? trim($inputs['others']) : null;
            $supplier->address      = !empty($inputs['address']) ? trim($inputs['address']) : null;
            $supplier->bank_details = !empty($inputs['bank_details']) ? trim($inputs['bank_details']) : null;

            if ($request->has('approval_status')) {
                $ap = $request->input('approval_status');
                $supplier->approval_status = ($ap === '' || $ap === null)
                    ? null
                    : (in_array($ap, ['red', 'green', 'yellow'], true) ? $ap : null);
            }

            if ($supplier->save()) {
                $msg = !empty($inputs['supplier_id'])
                    ? 'Supplier successfully updated.'
                    : 'Supplier successfully created.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => $msg,
                        'supplier' => ['id' => $supplier->id, 'name' => $supplier->name],
                    ]);
                }
                Session::flash('flash_message', $msg);
            } else {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Something went wrong while saving.']);
                }
                Session::flash('flash_message', 'Something went wrong while saving.');
            }
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 422);
            }
            Session::flash('flash_message', 'Error: ' . $e->getMessage());
        }

        return redirect()->back();
    }

    public function updateApprovalStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'approval_status' => 'nullable|in:red,green,yellow',
        ]);

        $supplier = Supplier::findOrFail($id);
        $supplier->approval_status = $validated['approval_status'] ?? null;
        $supplier->save();

        return response()->json([
            'success' => true,
            'approval_status' => $supplier->approval_status,
        ]);
    }

    function deleteSupplier($id)
    {
        $supplier = Supplier::findOrFail($id);
        if ($supplier->delete()) {
            Session::flash('flash_message', 'Supplier successfully deleted…');
        } else {
            Session::flash('flash_message', 'Something went wrong.');
        }
        return redirect()->back();
    }

    public function bulkImport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv,txt|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $header = array_map('strtolower', $rows[0]); // lowercased headers for consistency

            foreach (array_slice($rows, 1) as $row) {
                $data = array_combine($header, $row);

                if (empty($data['name'])) continue; // skip empty rows

                Supplier::create([
                    'type'          => $data['type'] ?? '',
                    'category_id'   => $data['category_id'] ?? null,
                    'name'          => $data['name'] ?? '',
                    'company'       => $data['company'] ?? '',
                    'sku'           => $data['sku'] ?? '',
                    'parent'        => $data['parent'] ?? '',
                    'country_code'  => $data['country_code'] ?? '',
                    'phone'         => $data['phone'] ?? '',
                    'city'          => $data['city'] ?? '',
                    'zone'          => $data['zone'] ?? '',
                    'email'         => $data['email'] ?? '',
                    'whatsapp'      => $data['whatsapp'] ?? '',
                    'wechat'        => $data['wechat'] ?? '',
                    'alibaba'       => $data['alibaba'] ?? '',
                    'others'        => $data['others'] ?? '',
                    'address'       => $data['address'] ?? '',
                    'bank_details'  => $data['bank_details'] ?? '',
                ]);
            }

            return redirect()->back()->with('flash_message', 'Suppliers imported successfully.');
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors(['file' => 'Invalid file format or structure.'])->withInput();
        }
    }

    //rating 
    public function storeRating(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'evaluation_date' => 'required|date',
            'rating_id' => 'nullable|integer|exists:supplier_ratings,id',
        ]);

        $criteriaRows = $request->input('criteria', []);
        if (! is_array($criteriaRows)) {
            $criteriaRows = [];
        }

        $criteria = [];
        $weightedPoints = 0.0;
        $weightSumFilled = 0.0;
        foreach ($criteriaRows as $c) {
            if (! is_array($c)) {
                continue;
            }
            $label = isset($c['label']) ? (string) $c['label'] : '';
            $weight = isset($c['weight']) ? (float) $c['weight'] : 0;
            $raw = $c['score'] ?? null;
            if ($raw === '' || $raw === null) {
                $criteria[] = ['label' => $label, 'weight' => $weight, 'score' => null];
                continue;
            }
            if (! is_numeric($raw)) {
                return redirect()->back()->withErrors(['criteria' => 'Each score must be a number between 0 and 10, or left blank.'])->withInput();
            }
            $score = (float) $raw;
            if ($score < 0 || $score > 10) {
                return redirect()->back()->withErrors(['criteria' => 'Scores must be between 0 and 10.'])->withInput();
            }
            $weightedPoints += $score * ($weight / 10);
            $weightSumFilled += $weight;
            $criteria[] = ['label' => $label, 'weight' => $weight, 'score' => $score];
        }

        $hasAnyScore = $weightSumFilled > 0;
        // Percentage 0–100 using only filled rows: 100 * sum(score*weight/10) / sum(weight_filled)
        $finalScore = $hasAnyScore
            ? round(100 * $weightedPoints / $weightSumFilled, 2)
            : null;

        $ratingId = $validated['rating_id'] ?? null;
        if ($ratingId) {
            $rating = SupplierRating::query()
                ->where('id', $ratingId)
                ->where('supplier_id', $validated['supplier_id'])
                ->firstOrFail();
            $rating->update([
                'evaluation_date' => $validated['evaluation_date'],
                'criteria' => $criteria,
                'final_score' => $finalScore,
            ]);
            $message = 'Supplier rating updated successfully.';
        } else {
            SupplierRating::create([
                'supplier_id' => $validated['supplier_id'],
                'evaluation_date' => $validated['evaluation_date'],
                'criteria' => $criteria,
                'final_score' => $finalScore,
            ]);
            $message = 'Supplier rating saved successfully!';
        }

        return redirect()->back()->with('flash_message', $message);
    }

}
