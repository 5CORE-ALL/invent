<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\RfqForm;
use App\Models\RfqSubmission;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class RFQController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        return view('purchase-master.rfq-form.index', compact('categories'));
    }

    public function getRfqFormsData(){
        $forms = RfqForm::select(
            'id',
            'name',
            'title',
            'slug',
            'main_image',
            'subtitle',
            'fields',
            'dimension_inner',
            'product_dimension',
            'package_dimension',
            'created_at',
            'updated_at'
            
        )->get();

        return response()->json([
            'data' => $forms
        ]);
    }

    public function storeRFQForm(Request $request)
    {
        $request->validate([
            'rfq_form_name' => 'required|string',
            'title' => 'required|string',
            'fields' => 'required|array',
            'main_image' => 'nullable|image|max:2048'
        ]);

        $slug = Str::slug($request->rfq_form_name) . '-' . Str::random(5);

        $imagePath = null;
        if($request->hasFile('main_image')){
            $imagePath = $request->file('main_image')->store('rfq_forms', 'public');
        }
        $fields = collect($request->fields)->map(function($field, $index) {
            $field['order'] = $field['order'] ?? ($index + 1);
            return $field;
        })->toArray();

        RfqForm::create([
            'name' => $request->rfq_form_name,
            'title' => $request->title,
            'slug' => $slug,
            'main_image' => $imagePath,
            'subtitle' => $request->subtitle,
            'fields' => $fields,
            'dimension_inner' => $request->dimension_inner,
            'product_dimension' => $request->product_dimension,
            'package_dimension' => $request->package_dimension,
        ]);

        return redirect()->back()->with('flash_message', 'RFQ Form created successfully!');
    }

    public function edit($id)
    {
        $form = RfqForm::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $form
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'rfq_form_name' => 'required|string',
            'title' => 'required|string',
            'fields_json' => 'required|string',
            'main_image' => 'nullable|image|max:2048'
        ]);

        $form = RfqForm::findOrFail($id);

        $imagePath = $form->main_image;
        if ($request->hasFile('main_image')) {
            $imagePath = $request->file('main_image')->store('rfq_forms', 'public');
        }

        $fields = json_decode($request->fields_json, true) ?? [];

        $form->update([
            'name' => $request->rfq_form_name,
            'title' => $request->title,
            'main_image' => $imagePath,
            'subtitle' => $request->subtitle,
            'fields' => $fields,
            'dimension_inner' => $request->dimension_inner,
            'product_dimension' => $request->product_dimension,
            'package_dimension' => $request->package_dimension,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'RFQ Form updated successfully!',
        ]);
    }

    public function destroy($id)
    {
        try {
            $form = RfqForm::findOrFail($id);
            $form->delete();

            return response()->json(['success' => true, 'message' => 'Form deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete form']);
        }
    }

    // Form Reports
    public function rfqReports($slug)
    {   
        $form = RfqForm::where('slug', $slug)->firstOrFail();
        return view('purchase-master.rfq-form.form-submit-reports', compact('form'));
    }

    public function getRfqReportsData($slug)
    {
        $form = RfqForm::where('id', $slug)->firstOrFail();
        $submissions = RfqSubmission::where('rfq_form_id', $form->id)
            ->select('id', 'data', 'created_at')
            ->get();

        return response()->json([
            'data' => $submissions
        ]);
    }

    public function searchSuppliers(Request $request)
    {
        try {
            $query = $request->get('q', '');
            
            if(strlen($query) < 2) {
                return response()->json([
                    'success' => true,
                    'suppliers' => []
                ]);
            }

            $suppliers = Supplier::where(function($q) use ($query) {
                    $q->where('name', 'LIKE', '%' . $query . '%')
                      ->orWhere('company', 'LIKE', '%' . $query . '%')
                      ->orWhere('email', 'LIKE', '%' . $query . '%');
                })
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->select('id', 'name', 'company', 'email')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'suppliers' => $suppliers
            ]);
        } catch (\Exception $e) {
            Log::error('Error in searchSuppliers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error searching suppliers',
                'suppliers' => []
            ], 500);
        }
    }

    public function sendEmailToSuppliers(Request $request)
    {
        try {
            $request->validate([
                'form_id' => 'required|exists:rfq_forms,id',
                'supplier_ids' => 'required|string',
                'email_subject' => 'required|string|max:255',
                'email_message' => 'nullable|string'
            ]);

            $form = RfqForm::findOrFail($request->form_id);
            $supplierIds = explode(',', $request->supplier_ids);
            $supplierIds = array_filter(array_map('trim', $supplierIds));

            if(empty($supplierIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No suppliers selected'
                ], 400);
            }

            $suppliers = Supplier::whereIn('id', $supplierIds)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->get();

            if($suppliers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid suppliers with email addresses found'
                ], 400);
            }

            $formUrl = url('/api/rfq-form/' . $form->slug);
            $sentCount = 0;
            $failedCount = 0;
            $errors = [];

            // Increase execution time limit for email sending
            set_time_limit(300); // 5 minutes
            ini_set('max_execution_time', 300);
            
            // Process emails in smaller batches to avoid timeout
            $batchSize = 2; // Process 2 emails at a time to avoid timeout
            $suppliersArray = $suppliers->toArray();
            
            for($i = 0; $i < count($suppliersArray); $i += $batchSize) {
                $batch = array_slice($suppliersArray, $i, $batchSize);
                
                foreach($batch as $supplierData) {
                    $supplier = (object) $supplierData;
                    try {
                        Mail::send('emails.rfq-form', [
                            'form' => $form,
                            'supplier' => $supplier,
                            'formUrl' => $formUrl,
                            'additionalMessage' => $request->email_message
                        ], function($message) use ($supplier, $request) {
                            $message->from(config('mail.from.address'), 'Purchase Department')
                                    ->to($supplier->email, $supplier->name ?? 'Supplier')
                                    ->subject($request->email_subject);
                        });
                        
                        $sentCount++;
                        
                        // Small delay to prevent overwhelming the SMTP server
                        usleep(500000); // 0.5 second delay
                        
                    } catch(\Exception $e) {
                        $failedCount++;
                        $errorMsg = $e->getMessage();
                        
                        // Check for timeout errors
                        if(strpos($errorMsg, 'timeout') !== false || 
                           strpos($errorMsg, 'Maximum execution time') !== false ||
                           strpos($errorMsg, 'Connection timed out') !== false) {
                            $errorMsg = 'SMTP connection timeout. Please check your mail server configuration.';
                        }
                        
                        $errors[] = $supplier->email . ': ' . $errorMsg;
                        Log::error('Failed to send RFQ email to supplier: ' . $supplier->email, [
                            'error' => $e->getMessage(),
                            'trace' => substr($e->getTraceAsString(), 0, 500)
                        ]);
                    }
                }
                
                // Longer delay between batches to give SMTP server time to recover
                if($i + $batchSize < count($suppliersArray)) {
                    sleep(2); // 2 second delay between batches
                }
            }

            return response()->json([
                'success' => $sentCount > 0,
                'message' => $sentCount > 0 ? 'Emails sent successfully' : 'Failed to send emails',
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'errors' => $errors
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in sendEmailToSuppliers: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error sending emails: ' . $e->getMessage()
            ], 500);
        }
    }

}
