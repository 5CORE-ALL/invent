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
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $inputs = $request->all();

        if (!empty($inputs['supplier_id'])) {
            $supplier = Supplier::findOrFail($inputs['supplier_id']);
        } else {
            $supplier = new Supplier;
        }

        $supplier->type         = $inputs['type'];
        $supplier->category_id  = isset($inputs['category_id']) ? implode(',', $inputs['category_id']) : null;
        $supplier->name         = $inputs['name'];
        $supplier->company      = $inputs['company']      ?? null;

        $supplier->parent       = $inputs['parent'];

        $supplier->country_code = $inputs['country_code'] ?? null;
        $supplier->phone        = $inputs['phone']        ?? null;
        $supplier->city         = $inputs['city']         ?? null;
        $supplier->email        = $inputs['email']        ?? null;
        $supplier->whatsapp     = $inputs['whatsapp']     ?? null;
        $supplier->wechat       = $inputs['wechat']       ?? null;
        $supplier->alibaba      = $inputs['alibaba']      ?? null;
        $supplier->website      = $inputs['website']      ?? null;
        $supplier->others       = $inputs['others']       ?? null;
        $supplier->address      = $inputs['address']      ?? null;
        $supplier->bank_details = $inputs['bank_details'] ?? null;

        if ($supplier->save()) {
            $msg = !empty($inputs['supplier_id']) ? 'Supplier successfully updated…' : 'Supplier successfully created…';
            Session::flash('flash_message', $msg);
        } else {
            Session::flash('flash_message', 'Something went wrong.');
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
