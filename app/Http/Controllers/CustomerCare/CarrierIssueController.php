<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\CustomerCare\Concerns\HasOptionalOrderNumberField;

class CarrierIssueController extends IssueBoardControllerBase
{
    use HasOptionalOrderNumberField;

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

    protected function moduleKey(): string
    {
        return 'carrier_issue';
    }
}
