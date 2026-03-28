<?php

namespace App\Http\Controllers\CustomerCare;

class CarrierIssueController extends IssueBoardControllerBase
{
    protected function viewName(): string
    {
        return 'customer-care.carrier_issue';
    }

    protected function issuesTable(): string
    {
        return 'carrier_issue_issues';
    }

    protected function historyTable(): string
    {
        return 'carrier_issue_issue_histories';
    }
}
