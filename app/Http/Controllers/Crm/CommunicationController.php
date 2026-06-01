<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\IndexCustomerTimelineRequest;
use App\Http\Requests\Crm\StoreCommunicationRequest;
use App\Http\Resources\Crm\CommunicationResource;
use App\Models\Crm\Customer;
use App\Services\Crm\Contracts\FollowUpServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function __construct(
        protected FollowUpServiceInterface $followUpService
    ) {}

    public function index(IndexCustomerTimelineRequest $request, Customer $customer): View|JsonResponse
    {
        $limit = $request->integer('limit', 100);
        $timeline = $this->followUpService->getCustomerTimeline($customer, $limit)->values();

        if ($this->wantsApiResponse($request)) {
            $data = $timeline->map(fn (array $row) => [
                'type' => $row['type'],
                'occurred_at' => $row['occurred_at']?->toIso8601String(),
                'title' => $row['title'],
                'meta' => $row['meta'],
            ]);

            return response()->json([
                'customer_id' => $customer->id,
                'data' => $data,
            ]);
        }

        return view('crm.communications.timeline', [
            'customer' => $customer,
            'timeline' => $timeline,
        ]);
    }

    public function store(StoreCommunicationRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('crm-attachments', 'public');
        }
        unset($data['attachment']);

        $log = $this->followUpService->addCommunication($data, $request->user());

        if ($this->wantsApiResponse($request)) {
            $log->loadMissing(['user', 'customer', 'followUp']);

            return CommunicationResource::make($log)
                ->response()
                ->setStatusCode(201);
        }

        return redirect()
            ->back()
            ->with('success', 'Communication logged.');
    }

    protected function wantsApiResponse(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson();
    }
}
