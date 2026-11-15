<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\CustomerCare\Concerns\AttachesAllIssuesLinkFields;
use App\Http\Controllers\CustomerCare\Concerns\HasOptionalOrderNumberField;
use Illuminate\Http\JsonResponse;

class CCareIssuesController extends IssueBoardControllerBase
{
    use AttachesAllIssuesLinkFields;
    use HasOptionalOrderNumberField;

    protected function viewName(): string
    {
        return 'customer-care.c_care_issues';
    }

    protected function issuesTable(): string
    {
        return 'c_care_issue_issues';
    }

    protected function historyTable(): string
    {
        return 'c_care_issue_issue_histories';
    }

    protected function moduleKey(): string
    {
        return 'c_care_issues';
    }

    /** Tracking ID and Link are read-only here; they come from All Issues by order number. */
    public function issuesIndex(): JsonResponse
    {
        $body = parent::issuesIndex()->getData(true);
        $body['data'] = $this->attachAllIssuesLinkFields($body['data'] ?? []);

        return response()->json($body);
    }
}
