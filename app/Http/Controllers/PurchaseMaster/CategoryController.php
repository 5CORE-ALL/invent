<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function categoryList()
    {
        $categories = Category::paginate(20);

        foreach ($categories as $category) {
            $category->supplier_count = DB::table('suppliers')
                ->whereRaw("FIND_IN_SET(?, category_id)", [$category->id])
                ->count();
        }
        return view('purchase-master.category.category_list', compact('categories'));
    }

    public function postCategory(Request $request)
    {
        $data = $request->except('_token');

        $rule = [
            'category_name' => 'required'
        ];

        $validator = Validator::make($data, $rule);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $inputs = $request->all();
        if (!empty($inputs['category_id'])) {
            $category_obj = Category::findOrFail($inputs['category_id']);
        } else {
            $category_obj = new Category;
        }

        $category_obj->name = $inputs['category_name'];
        $category_obj->status = isset($inputs['status']) ? $inputs['status'] : 'inactive';

        $fields = [];
        if (!empty($inputs['field_name']) && !empty($inputs['field_label'])) {
            foreach ($inputs['field_name'] as $key => $name) {
                $label = $inputs['field_label'][$key] ?? null;
                $type  = $inputs['field_type'][$key] ?? 'text';

                if (!empty($name) && !empty($label)) {
                    $fields[] = [
                        'name'  => $name,
                        'label' => $label,
                        'type'  => $type,
                    ];
                }
            }
        }
        $category_obj->fields = $fields;
        $category_obj->save();

        $message = !empty($inputs['category_id']) 
            ? 'Successfully updated category.' 
            : 'Successfully created category.';

        return redirect()->back()->with('status', $message);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        return redirect()->back()->with('flash_message', 'Category deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids');
        if (!is_array($ids) || empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No categories selected.']);
        }

        Category::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => 'Categories deleted.']);
    }


}
