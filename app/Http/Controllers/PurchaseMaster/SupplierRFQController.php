<?php

namespace App\Http\Controllers\PurchaseMaster;

use Illuminate\Routing\Controller as BaseController;
use App\Models\RfqForm;
use App\Models\RfqSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SupplierRFQController extends BaseController
{
    public function index()
    {
        return "Hello from SupplierRFQController";
    }
    
    public function showRfqForm(Request $request, $slug)
    {
        $rfqForm = RfqForm::where('slug', $slug)->firstOrFail();

        // Form part: basics | details | all (default all)
        $part = strtolower((string) $request->query('part', 'all'));
        if (!in_array($part, ['basics', 'details', 'all'], true)) {
            $part = 'all';
        }

        // Per-supplier token ties Basics + Details into one record
        $token = $request->query('token');

        // Only logged-in users with a @5core.com email may edit the form.
        $user = Auth::user();
        $canEdit = $user && Str::endsWith(strtolower((string) ($user->email ?? '')), '@5core.com');
        $editUrl = $canEdit ? url('/rfq-form/list?edit=' . $rfqForm->id) : null;

        return view('purchase-master.rfq-form.rfq-form', compact('rfqForm', 'canEdit', 'editUrl', 'part', 'token'));
    }

    public function submitRfqForm(Request $request, $slug)
    {
        $form = RfqForm::where('slug', $slug)->firstOrFail();

        $part = strtolower((string) $request->input('part', 'all'));
        if (!in_array($part, ['basics', 'details', 'all'], true)) {
            $part = 'all';
        }
        $token = $request->input('token');

        // Dynamic fields belong to the "Details" part; only validate them when details are being submitted.
        $rules = [];
        if (in_array($part, ['details', 'all'], true)) {
            foreach ($form->fields as $field) {
                if (!empty($field['required'])) {
                    $rules[$field['name']] = 'required';
                }
            }
        }

        if ($request->hasFile('additionalPhotos')) {
            $rules['additionalPhotos.*'] = 'image|mimes:jpg,jpeg,png|max:2048';
        }

        $request->validate($rules);

        $data = $request->except(['_token', 'token', 'part']);

        if ($request->hasFile('additionalPhotos')) {
            $paths = [];
            foreach ($request->file('additionalPhotos') as $file) {
                $paths[] = $file->store('rfq_uploads', 'public');
            }
            $data['additionalPhotos'] = $paths;
        }

        // Decide how to merge the two parts (Basics / Details) into one supplier row:
        //   1. If a per-supplier token is present, merge by token (most reliable).
        //   2. Otherwise, merge by Supplier Name within the same form, so sending the
        //      Basics link first and the Details link later still lands in one row.
        $submission = null;

        if (!empty($token)) {
            $submission = RfqSubmission::firstOrNew([
                'rfq_form_id' => $form->id,
                'token' => $token,
            ]);
        } elseif (!empty($data['supplierName'])) {
            $submission = RfqSubmission::where('rfq_form_id', $form->id)
                ->whereNull('token')
                ->where('data->supplierName', $data['supplierName'])
                ->first();

            if (!$submission) {
                $submission = new RfqSubmission(['rfq_form_id' => $form->id]);
            }
        }

        if ($submission) {
            $existing = $submission->data ?? [];
            if (!is_array($existing)) {
                $existing = [];
            }

            foreach ($data as $key => $value) {
                // Don't overwrite an existing value with an empty one (keeps the previously submitted part intact)
                if (($value === null || $value === '') && array_key_exists($key, $existing)) {
                    continue;
                }
                $existing[$key] = $value;
            }

            $submission->rfq_form_id = $form->id;
            $submission->data = $existing;
            $submission->save();
        } else {
            RfqSubmission::create([
                'rfq_form_id' => $form->id,
                'data' => $data,
            ]);
        }

        $message = "🎉 Thank you for submitting your quotation! We have successfully received your details. Our team will review your submission and contact you shortly.";
        return redirect()->back()->with('success', $message);
    }
}
