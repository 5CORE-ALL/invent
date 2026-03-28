<?php

namespace App\Http\Controllers\CustomerCare;

class OtherIssuesController extends IssueBoardControllerBase
{
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
