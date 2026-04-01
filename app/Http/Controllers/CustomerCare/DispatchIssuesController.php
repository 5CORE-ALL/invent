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

    protected function extraValidationRules(): array
    {
        return [
            'order_number'  => 'nullable|string|max:255',
            'refund_amount' => 'nullable|numeric|min:0',
            'total_loss'    => 'nullable|numeric',
        ];
    }

    protected function buildExtraPayload(array $validated): array
    {
        return [
            'order_number'  => isset($validated['order_number'])  ? trim((string) $validated['order_number'])  : null,
            'refund_amount' => isset($validated['refund_amount'])  ? (float) $validated['refund_amount']         : null,
            'total_loss'    => isset($validated['total_loss'])     ? (float) $validated['total_loss']            : null,
        ];
    }

    protected function extraRowFields(object $row): array
    {
        return [
            'order_number'  => $row->order_number  ?? null,
            'refund_amount' => $row->refund_amount  !== null ? (float) $row->refund_amount : null,
            'total_loss'    => $row->total_loss     !== null ? (float) $row->total_loss    : null,
        ];
    }
}
