<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\CustomerCare\Concerns\HasOptionalOrderNumberField;

class OtherIssuesController extends IssueBoardControllerBase
{
    use HasOptionalOrderNumberField;

    protected function viewName(): string
    {
        return 'customer-care.other_issues';
    }

    protected function issuesTable(): string
    {
        return 'other_issue_issues';
    }

    protected function historyTable(): string
    {
        return 'other_issue_issue_histories';
    }

    protected function moduleKey(): string
    {
        return 'other_issues';
    }
}
