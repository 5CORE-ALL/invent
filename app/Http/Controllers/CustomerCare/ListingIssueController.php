<?php

namespace App\Http\Controllers\CustomerCare;

class ListingIssueController extends IssueBoardControllerBase
{
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
