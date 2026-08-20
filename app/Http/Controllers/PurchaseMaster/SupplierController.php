<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\SupplierBankAccount;
use App\Models\SupplierBankAccountHistory;
use App\Models\SupplierRating;
use App\Models\SupplierRemarkHistory;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                  ->orWhere('alias', 'LIKE', '%' . $search . '%')
                  ->orWhere('email', 'LIKE', '%' . $search . '%')
                  ->orWhere('phone', 'LIKE', '%' . $search . '%');
            });
        }

        // Apply sorting (whitelisted to prevent SQL injection)
        $sortableColumns = [
            'category' => 'category_id',
            'name'     => 'name',
            'approval' => 'approval_status',
            'company'  => 'company',
            'alias'    => 'alias',
            'parent'   => 'parent',
            'zone'     => 'zone',
            'phone'    => 'phone',
            'rating'   => null,            // computed via correlated subquery
            'alibaba'  => 'alibaba',
            'link_1688'=> 'link_1688',
            'qq'       => 'qq',
            'email'    => 'email',
            'whatsapp' => 'whatsapp',
            'wechat'   => 'wechat',
        ];

        $sortKey   = $request->get('sort');
        $direction = strtolower((string) $request->get('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sortKey && array_key_exists($sortKey, $sortableColumns)) {
            if ($sortKey === 'rating') {
                $query->orderByRaw(
                    '(SELECT AVG(final_score) FROM supplier_ratings WHERE supplier_ratings.supplier_id = suppliers.id) ' . $direction
                );
                $query->orderBy('name', 'asc');
            } else {
                $column = $sortableColumns[$sortKey];
                $query->orderBy($column, $direction);
                if ($column !== 'name') {
                    $query->orderBy('name', 'asc');
                }
            }
        } else {
            // No explicit sort requested — keep prior behaviour (default MySQL order).
            $sortKey   = '';
            $direction = 'asc';
        }

        // Get total count before pagination (for filtered results)
        $filteredCount = $query->count();
        
        // Get total count of all suppliers (unfiltered)
        $totalCount = Supplier::count();
        
        $suppliers = $query->with(['ratings', 'latestRemark', 'latestAdvance'])
            ->withCount('bankAccounts')
            ->paginate(20)
            ->appends($request->query());
        $categories = $this->categoriesWithSupplierCounts();
        $canEditSupplierBank = $this->canEditSupplierBank();
        $bankSupplierNames = Supplier::distinctNamesForListPage()->values()->all();

        // If AJAX request, return JSON
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'html' => view('purchase-master.supplier.partials.rows', compact('suppliers', 'categories', 'canEditSupplierBank'))->render(),
                'pagination' => (string) $suppliers->onEachSide(1)->links('pagination::bootstrap-5'),
                'currentPage' => $suppliers->currentPage(),
                'lastPage' => max(1, $suppliers->lastPage()),
                'filteredCount' => $filteredCount,
                'totalCount' => $totalCount,
                'sort' => $sortKey,
                'direction' => $direction,
            ]);
        }

        return view('purchase-master.supplier.suppliers' , compact(
            'suppliers',
            'categories',
            'filteredCount',
            'totalCount',
            'sortKey',
            'direction',
            'canEditSupplierBank',
            'bankSupplierNames'
        ));
    }

    /**
     * Bank account edit allowed only for Sruit / Candy / President.
     */
    protected function canEditSupplierBank(): bool
    {
        $email = strtolower(trim((string) (auth()->user()->email ?? '')));

        return in_array($email, [
            'sourcing1@5core.com',
            'purchase@5core.com',
            'president@5core.com',
        ], true);
    }

    protected function bankAccountFields(): array
    {
        return [
            'supplier_name',
            'nick_name',
            'company_name',
            'swift',
            'address',
            'city',
            'province',
            'country',
            'account_number',
            'acc_type',
        ];
    }

    /**
     * Beneficiary + Address: letters, numbers, spaces only (no special characters).
     *
     * @return list<string>
     */
    protected function bankAccountNoSpecialCharFields(): array
    {
        return ['company_name', 'address'];
    }

    protected function bankAccountFieldRules(): array
    {
        $rules = [];
        foreach ($this->bankAccountFields() as $field) {
            if ($field === 'acc_type') {
                $rules[$field] = ['required', 'string', 'in:RMB,USD'];
                continue;
            }
            if ($field === 'country') {
                $rules[$field] = ['required', 'string', 'in:China,India,Hong Kong'];
                continue;
            }
            if ($field === 'province') {
                $rules[$field] = ['required', 'string', 'in:'.implode(',', config('supplier_bank.provinces', []))];
                continue;
            }
            $rules[$field] = ['required', 'string', 'min:1', 'max:50'];
            if (in_array($field, $this->bankAccountNoSpecialCharFields(), true)) {
                // Letters (any language), numbers, spaces only.
                $rules[$field][] = 'regex:/^[\p{L}\p{N}\s]*$/u';
            }
        }

        return $rules;
    }

    protected function bankAccountValidationMessages(): array
    {
        return [
            'supplier_name.required' => 'Supplier name is required.',
            'nick_name.required' => 'Nick name is required.',
            'company_name.required' => 'Beneficiary is required.',
            'company_name.regex' => 'Beneficiary cannot contain special characters.',
            'swift.required' => 'Swift is required.',
            'address.required' => 'Address is required.',
            'address.regex' => 'Address cannot contain special characters.',
            'city.required' => 'City is required.',
            'province.required' => 'Province is required.',
            'province.in' => 'Please select a valid province.',
            'country.required' => 'Country is required.',
            'country.in' => 'Country must be China, India, or Hong Kong.',
            'account_number.required' => 'Account number is required.',
            'acc_type.required' => 'Acc Type is required.',
            'acc_type.in' => 'Acc Type must be RMB or US $.',
        ];
    }

    protected function authBankUserName(): string
    {
        $name = trim((string) (auth()->user()->name ?? ''));
        if ($name === '') {
            return 'Unknown';
        }

        return explode(' ', $name)[0] ?: 'Unknown';
    }

    public function getSupplierBankAccounts($id)
    {
        $supplier = Supplier::findOrFail($id);
        $accounts = SupplierBankAccount::where('supplier_id', $supplier->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'can_edit' => $this->canEditSupplierBank(),
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'company' => $supplier->company,
            ],
            'accounts' => $accounts,
        ]);
    }

    public function storeSupplierBankAccount(Request $request, $id)
    {
        if (!$this->canEditSupplierBank()) {
            return response()->json(['success' => false, 'message' => 'You are not allowed to edit bank details.'], 403);
        }

        $supplier = Supplier::findOrFail($id);
        $data = $request->validate(
            $this->bankAccountFieldRules(),
            $this->bankAccountValidationMessages()
        );

        foreach ($this->bankAccountFields() as $field) {
            $data[$field] = trim((string) ($data[$field] ?? ''));
        }

        $account = SupplierBankAccount::create(array_merge($data, [
            'supplier_id' => $supplier->id,
        ]));

        SupplierBankAccountHistory::create([
            'supplier_id' => $supplier->id,
            'supplier_bank_account_id' => $account->id,
            'user_id' => auth()->id(),
            'user_name' => $this->authBankUserName(),
            'action' => 'created',
            'changes' => ['new' => $account->only($this->bankAccountFields())],
        ]);

        return response()->json([
            'success' => true,
            'account' => $account,
            'message' => 'Bank account added.',
        ]);
    }

    public function updateSupplierBankAccount(Request $request, $id, $accountId)
    {
        if (!$this->canEditSupplierBank()) {
            return response()->json(['success' => false, 'message' => 'You are not allowed to edit bank details.'], 403);
        }

        $supplier = Supplier::findOrFail($id);
        $account = SupplierBankAccount::where('supplier_id', $supplier->id)
            ->where('id', $accountId)
            ->firstOrFail();

        $data = $request->validate(
            $this->bankAccountFieldRules(),
            $this->bankAccountValidationMessages()
        );

        $old = $account->only($this->bankAccountFields());
        $changes = [];
        foreach ($this->bankAccountFields() as $field) {
            $val = trim((string) ($data[$field] ?? ''));
            if ((string) ($old[$field] ?? '') !== $val) {
                $changes[$field] = ['old' => $old[$field], 'new' => $val];
            }
            $account->{$field} = $val;
        }

        if ($changes === []) {
            return response()->json(['success' => true, 'account' => $account, 'message' => 'No changes.']);
        }

        $account->save();

        SupplierBankAccountHistory::create([
            'supplier_id' => $supplier->id,
            'supplier_bank_account_id' => $account->id,
            'user_id' => auth()->id(),
            'user_name' => $this->authBankUserName(),
            'action' => 'updated',
            'changes' => $changes,
        ]);

        return response()->json([
            'success' => true,
            'account' => $account,
            'message' => 'Bank account updated.',
        ]);
    }

    public function deleteSupplierBankAccount($id, $accountId)
    {
        if (!$this->canEditSupplierBank()) {
            return response()->json(['success' => false, 'message' => 'You are not allowed to edit bank details.'], 403);
        }

        $supplier = Supplier::findOrFail($id);
        $account = SupplierBankAccount::where('supplier_id', $supplier->id)
            ->where('id', $accountId)
            ->firstOrFail();

        $snapshot = $account->only($this->bankAccountFields());
        $account->delete();

        SupplierBankAccountHistory::create([
            'supplier_id' => $supplier->id,
            'supplier_bank_account_id' => null,
            'user_id' => auth()->id(),
            'user_name' => $this->authBankUserName(),
            'action' => 'deleted',
            'changes' => ['old' => $snapshot],
        ]);

        return response()->json(['success' => true, 'message' => 'Bank account deleted.']);
    }

    public function getSupplierBankHistory($id)
    {
        $supplier = Supplier::findOrFail($id);
        $history = SupplierBankAccountHistory::where('supplier_id', $supplier->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (SupplierBankAccountHistory $item) {
                $at = $item->created_at;
                return [
                    'id' => $item->id,
                    'action' => $item->action,
                    'user_name' => $item->user_name ?: 'Unknown',
                    'account_id' => $item->supplier_bank_account_id,
                    'changes' => $item->changes,
                    'date_label' => $at ? ($at->format('j') . strtoupper($at->format('M'))) : '',
                    'created_at' => $at?->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }

    /**
     * Categories with supplier_count per category (same logic as category list page).
     */
    private function categoriesWithSupplierCounts(): Collection
    {
        $categories = Category::orderBy('name')->get();
        foreach ($categories as $category) {
            $category->supplier_count = DB::table('suppliers')
                ->whereRaw('FIND_IN_SET(?, category_id)', [$category->id])
                ->count();
        }

        return $categories;
    }

    /**
     * Export the (optionally filtered) supplier list to a CSV file.
     * Honours the same category / type / search filters as the list page.
     */
    public function exportSuppliers(Request $request)
    {
        $query = Supplier::query();

        if ($request->filled('category')) {
            $category = Category::where('name', $request->get('category'))->first();
            if ($category) {
                $query->where('category_id', 'LIKE', '%' . $category->id . '%');
            }
        }

        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                  ->orWhere('company', 'LIKE', '%' . $search . '%')
                  ->orWhere('alias', 'LIKE', '%' . $search . '%')
                  ->orWhere('email', 'LIKE', '%' . $search . '%')
                  ->orWhere('phone', 'LIKE', '%' . $search . '%');
            });
        }

        $categoryNameById = Category::pluck('name', 'id');

        $resolveCategoryNames = function ($categoryId) use ($categoryNameById) {
            if ($categoryId === null || $categoryId === '') {
                return '';
            }
            $ids = preg_split('/[,\s]+/', (string) $categoryId, -1, PREG_SPLIT_NO_EMPTY);
            $names = [];
            foreach ($ids as $id) {
                if (isset($categoryNameById[$id])) {
                    $names[] = $categoryNameById[$id];
                }
            }
            return implode(', ', $names);
        };

        $columns = [
            'Type', 'Category', 'Name', 'Company', 'Alias', 'Parent', 'Phone', 'City', 'Zone',
            'Email', 'WhatsApp', 'WeChat', 'Alibaba', '1688', 'QQ', 'Others', 'Address', 'Approval Status',
        ];

        // Fetch all rows up-front. Running queries inside a streamed callback can
        // fail ("prepare() on null") because the DB connection may be gone by the
        // time the callback executes, so build the full CSV string here instead.
        $suppliers = $query->orderBy('name')->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $columns);
        foreach ($suppliers as $s) {
            fputcsv($handle, [
                $s->type ?? '',
                $resolveCategoryNames($s->category_id ?? ''),
                $s->name ?? '',
                $s->company ?? '',
                $s->alias ?? '',
                $s->parent ?? '',
                $s->phone ?? '',
                $s->city ?? '',
                $s->zone ?? '',
                $s->email ?? '',
                $s->whatsapp ?? '',
                $s->wechat ?? '',
                $s->alibaba ?? '',
                $s->link_1688 ?? '',
                $s->qq ?? '',
                $s->others ?? '',
                $s->address ?? '',
                $s->approval_status ?? '',
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'suppliers-' . now()->format('Y-m-d_His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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
            $supplier->alias        = !empty($inputs['alias']) ? trim($inputs['alias']) : null;
            $supplier->parent       = !empty($inputs['parent']) ? trim($inputs['parent']) : null;
            $supplier->country_code = !empty($inputs['country_code']) ? trim($inputs['country_code']) : null;
            $supplier->phone        = !empty($inputs['phone']) ? trim($inputs['phone']) : null;
            $supplier->city         = !empty($inputs['city']) ? trim($inputs['city']) : null;
            $supplier->zone         = !empty($inputs['zone']) ? trim($inputs['zone']) : null;
            $supplier->email        = !empty($inputs['email']) ? trim($inputs['email']) : null;
            $supplier->whatsapp     = !empty($inputs['whatsapp']) ? trim($inputs['whatsapp']) : null;
            $supplier->wechat       = !empty($inputs['wechat']) ? trim($inputs['wechat']) : null;
            $supplier->alibaba      = !empty($inputs['alibaba']) ? trim($inputs['alibaba']) : null;
            $supplier->link_1688    = !empty($inputs['link_1688']) ? trim($inputs['link_1688']) : null;
            $supplier->qq           = !empty($inputs['qq']) ? trim($inputs['qq']) : null;
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
                    'alias'         => $data['alias'] ?? '',
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
                    'link_1688'     => $data['link_1688'] ?? $data['1688'] ?? '',
                    'qq'            => $data['qq'] ?? '',
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
