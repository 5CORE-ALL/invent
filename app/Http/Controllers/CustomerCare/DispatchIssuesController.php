<?php

namespace App\Http\Controllers\CustomerCare;

class DispatchIssuesController extends IssueBoardControllerBase
{
    protected function viewName(): string
    {
        return 'customer-care.dispatch_issues';
    }

    protected function issuesTable(): string
    {
        return 'dispatch_issue_issues';
    }

    protected function historyTable(): string
    {
        return 'dispatch_issue_issue_histories';
    }

    protected function moduleKey(): string
    {
        return 'dispatch_issues';
    }
}
