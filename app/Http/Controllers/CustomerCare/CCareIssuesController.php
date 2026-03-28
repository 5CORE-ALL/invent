<?php

namespace App\Http\Controllers\CustomerCare;

class CCareIssuesController extends IssueBoardControllerBase
{
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
}
