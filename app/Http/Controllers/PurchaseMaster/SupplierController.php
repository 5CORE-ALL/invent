<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\SupplierRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SupplierController extends Controller
{
    function supplierList()
    {
        $suppliers = Supplier::paginate(20);
        $categories = Category::orderBy('name')->get();
        return view('purchase-master.supplier.suppliers' , compact('suppliers', 'categories'));
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

            if ($supplier->save()) {
                $msg = !empty($inputs['supplier_id']) 
                    ? 'Supplier successfully updated.' 
                    : 'Supplier successfully created.';
                Session::flash('flash_message', $msg);
            } else {
                Session::flash('flash_message', 'Something went wrong while saving.');
            }
        } catch (\Exception $e) {
            Session::flash('flash_message', 'Error: ' . $e->getMessage());
        }

        return redirect()->back();
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
            'criteria' => 'required|array',
        ]);

        $total = 0;
        foreach ($validated['criteria'] as $c) {
            $score = (float) $c['score'];
            $weight = (float) $c['weight'];
            $total += $score * ($weight / 10);
        }

        SupplierRating::create([
            'supplier_id'    => $validated['supplier_id'],
            'evaluation_date'=> $validated['evaluation_date'],
            'criteria'       => $validated['criteria'],
            'final_score'    => round($total, 2),
        ]);


        return redirect()->back()->with('flash_message', 'Supplier rating saved successfully!');
    }

}
