<?php

namespace App\Http\Controllers\PurchaseMaster;

use Illuminate\Routing\Controller as BaseController;
use App\Models\RfqForm;
use App\Models\RfqSubmission;
use Illuminate\Http\Request;

class SupplierRFQController extends BaseController
{
    public function index()
    {
        return "Hello from SupplierRFQController";
    }
    
    public function showRfqForm($slug)
    {
        $rfqForm = RfqForm::where('slug', $slug)->firstOrFail();

        return view('purchase-master.rfq-form.rfq-form', compact('rfqForm'));
    }

    public function submitRfqForm(Request $request, $slug)
    {
        $form = RfqForm::where('slug', $slug)->firstOrFail();

        $rules = [];
        foreach ($form->fields as $field) {
            if (!empty($field['required'])) {
                $rules[$field['name']] = 'required';
            }
        }

        if ($request->hasFile('additionalPhotos')) {
            $rules['additionalPhotos.*'] = 'image|mimes:jpg,jpeg,png|max:2048';
        }

        $request->validate($rules);

        $data = $request->except('_token');

        if ($request->hasFile('additionalPhotos')) {
            $paths = [];
            foreach ($request->file('additionalPhotos') as $file) {
                $paths[] = $file->store('rfq_uploads', 'public');
            }
            $data['additionalPhotos'] = $paths;
        }

        RfqSubmission::create([
            'rfq_form_id' => $form->id,
            'data' => $data
        ]);

        $message = "🎉 Thank you for submitting your quotation! We have successfully received your details. Our team will review your submission and contact you shortly.";
        return redirect()->back()->with('success', $message);
    }
}
