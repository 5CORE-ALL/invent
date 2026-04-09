<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\ChangeFollowUpStatusRequest;
use App\Http\Requests\Crm\IndexFollowUpRequest;
use App\Http\Requests\Crm\StoreFollowUpRequest;
use App\Http\Requests\Crm\UpdateFollowUpRequest;
use App\Http\Resources\Crm\FollowUpResource;
use App\Models\Crm\Customer;
use App\Models\Crm\FollowUp;
use App\Models\User;
use App\Services\Crm\Contracts\FollowUpServiceInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FollowUpController extends Controller
{
    public function __construct(
        protected FollowUpServiceInterface $followUpService
    ) {}

    public function index(IndexFollowUpRequest $request): View|JsonResponse
    {
        $q = $this->followUpsFilteredQuery($request)
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id');

        $perPage = $request->integer('per_page', 15);
        $paginator = $q->paginate($perPage)->withQueryString();

        if ($this->wantsApiResponse($request)) {
            return FollowUpResource::collection($paginator);
        }

        return view('crm.follow-ups.index', [
            'followUps' => $paginator,
            'filters' => $request->only(['customer_id', 'status', 'per_page']),
        ]);
    }

    public function exportCsv(IndexFollowUpRequest $request): StreamedResponse
    {
        $filename = 'follow-ups-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'id',
                'customer_id',
                'customer_name',
                'company_id',
                'assigned_user_id',
                'assignee_name',
                'title',
                'description',
                'follow_up_type',
                'priority',
                'status',
                'outcome',
                'scheduled_at',
                'reminder_at',
                'next_follow_up_at',
                'reminder_notified_at',
                'created_at',
                'updated_at',
            ]);

            $this->followUpsFilteredQuery($request)
                ->select([
                    'id',
                    'customer_id',
                    'company_id',
                    'assigned_user_id',
                    'title',
                    'description',
                    'follow_up_type',
                    'priority',
                    'status',
                    'outcome',
                    'scheduled_at',
                    'reminder_at',
                    'next_follow_up_at',
                    'reminder_notified_at',
                    'created_at',
                    'updated_at',
                ])
                ->with([
                    'customer:id,name',
                    'assignedUser:id,name',
                ])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($out): void {
                    foreach ($rows as $fu) {
                        fputcsv($out, [
                            $fu->id,
                            $fu->customer_id,
                            $fu->customer?->name,
                            $fu->company_id,
                            $fu->assigned_user_id,
                            $fu->assignedUser?->name,
                            $fu->title,
                            $fu->description,
                            $fu->follow_up_type,
                            $fu->priority,
                            $fu->status,
                            $fu->outcome,
                            $fu->scheduled_at?->toIso8601String(),
                            $fu->reminder_at?->toIso8601String(),
                            $fu->next_follow_up_at?->toIso8601String(),
                            $fu->reminder_notified_at?->toIso8601String(),
                            $fu->created_at?->toIso8601String(),
                            $fu->updated_at?->toIso8601String(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create(): View
    {
        return view('crm.follow-ups.create', [
            'customers' => Customer::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(StoreFollowUpRequest $request): JsonResponse|RedirectResponse
    {
        $followUp = $this->followUpService->createFollowUp($request->validated(), $request->user());

        if ($this->wantsApiResponse($request)) {
            return FollowUpResource::make($followUp->loadMissing(['customer', 'assignedUser', 'company']))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()
            ->route('crm.follow-ups.show', $followUp)
            ->with('success', 'Follow-up created.');
    }

    public function show(Request $request, FollowUp $follow_up): View|JsonResponse
    {
        $follow_up->load(['customer', 'assignedUser', 'company', 'statusHistories.changedByUser']);

        if ($this->wantsApiResponse($request)) {
            return FollowUpResource::make($follow_up);
        }

        return view('crm.follow-ups.show', ['followUp' => $follow_up]);
    }

    public function edit(FollowUp $follow_up): View
    {
        $users = User::query()
            ->where(function ($q) use ($follow_up) {
                $q->where('is_active', true)->orWhere('id', $follow_up->assigned_user_id);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('crm.follow-ups.edit', [
            'followUp' => $follow_up,
            'users' => $users,
        ]);
    }

    public function update(UpdateFollowUpRequest $request, FollowUp $follow_up): JsonResponse|RedirectResponse
    {
        $follow_up = $this->followUpService->updateFollowUp($follow_up, $request->validated(), $request->user());

        if ($this->wantsApiResponse($request)) {
            return FollowUpResource::make($follow_up->loadMissing(['customer', 'assignedUser', 'company']));
        }

        return redirect()
            ->route('crm.follow-ups.show', $follow_up)
            ->with('success', 'Follow-up updated.');
    }

    public function changeStatus(ChangeFollowUpStatusRequest $request, FollowUp $follow_up): JsonResponse|RedirectResponse
    {
        $follow_up = $this->followUpService->changeStatus(
            $follow_up,
            (string) $request->input('status'),
            $request->user()
        );

        if ($this->wantsApiResponse($request)) {
            return FollowUpResource::make($follow_up->loadMissing(['customer', 'assignedUser', 'company']));
        }

        return redirect()
            ->back()
            ->with('success', 'Status updated.');
    }

    public function destroy(Request $request, FollowUp $follow_up): JsonResponse|RedirectResponse
    {
        $follow_up->delete();

        if ($this->wantsApiResponse($request)) {
            return response()->json(null, 204);
        }

        return redirect()
            ->route('crm.follow-ups.index')
            ->with('success', 'Follow-up deleted.');
    }

    protected function wantsApiResponse(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson();
    }

    protected function followUpsFilteredQuery(IndexFollowUpRequest $request): Builder
    {
        $q = FollowUp::query()->with(['customer', 'assignedUser', 'company']);

        if ($request->filled('customer_id')) {
            $q->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }

        return $q;
    }
}
