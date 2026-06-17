<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\CustomerCare\Concerns\HasOptionalOrderNumberField;

class ListingIssueController extends IssueBoardControllerBase
{
    use HasOptionalOrderNumberField;

    protected function viewName(): string
    {
        return 'customer-care.listing_issue';
    }

    protected function issuesTable(): string
    {
        return 'listing_issue_issues';
    }

    protected function historyTable(): string
    {
        return 'listing_issue_issue_histories';
    }

    protected function moduleKey(): string
    {
        return 'listing_issue';
    }
}
