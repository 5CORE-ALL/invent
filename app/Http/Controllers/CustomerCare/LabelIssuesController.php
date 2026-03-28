<?php

namespace App\Http\Controllers\CustomerCare;

class LabelIssuesController extends IssueBoardControllerBase
{
    protected function viewName(): string
    {
        return 'customer-care.label_issues';
    }

    protected function issuesTable(): string
    {
        return 'label_issue_issues';
    }

    protected function historyTable(): string
    {
        return 'label_issue_issue_histories';
    }

    protected function moduleKey(): string
    {
        return 'label_issues';
    }
}
