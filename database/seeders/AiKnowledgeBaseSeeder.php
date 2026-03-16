<?php

namespace Database\Seeders;

use App\Models\AiKnowledgeBase;
use Illuminate\Database\Seeder;

class AiKnowledgeBaseSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [
                'category' => 'Task',
                'subcategory' => 'Assignment',
                'question_pattern' => 'how to assign task',
                'answer_steps' => [
                    '1. Navigate to Tasks section from main dashboard',
                    '2. Click "Create New Task" button',
                    '3. Fill in task title and description',
                    '4. Select assignee from team members list',
                    '5. Set due date and priority level',
                    '6. Click "Assign Task" to confirm',
                ],
                'video_link' => null,
                'tags' => ['task', 'assign', 'dashboard', 'workflow'],
            ],
            [
                'category' => 'HR',
                'subcategory' => 'Leave',
                'question_pattern' => 'how to apply for leave',
                'answer_steps' => [
                    '1. Go to HR section in the main menu',
                    '2. Select "Leave Application"',
                    '3. Choose leave type (annual, sick, etc.)',
                    '4. Enter start and end dates',
                    '5. Add reason or comment if required',
                    '6. Submit for manager approval',
                ],
                'video_link' => null,
                'tags' => ['leave', 'hr', 'application', 'holiday'],
            ],
            [
                'category' => 'Finance',
                'subcategory' => 'Invoice',
                'question_pattern' => 'how to create invoice',
                'answer_steps' => [
                    '1. Open Finance or Sales module',
                    '2. Click "New Invoice"',
                    '3. Select client and project or order',
                    '4. Add line items with description and amount',
                    '5. Apply tax and discount if needed',
                    '6. Review and save or send to client',
                ],
                'video_link' => null,
                'tags' => ['invoice', 'finance', 'billing', 'client'],
            ],
            [
                'category' => 'Sales',
                'subcategory' => 'Onboarding',
                'question_pattern' => 'new client onboarding',
                'answer_steps' => [
                    '1. Create new client record in CRM',
                    '2. Add contact details and account manager',
                    '3. Set up contract and pricing in the system',
                    '4. Schedule kick-off meeting and add to calendar',
                    '5. Share access or credentials as per agreement',
                    '6. Add client to relevant distribution lists',
                ],
                'video_link' => null,
                'tags' => ['client', 'onboarding', 'sales', 'crm'],
            ],
            [
                'category' => 'Finance',
                'subcategory' => 'Expenses',
                'question_pattern' => 'expense reimbursement',
                'answer_steps' => [
                    '1. Go to Finance > Expense claims',
                    '2. Click "Submit new expense"',
                    '3. Attach receipts or supporting documents',
                    '4. Enter amount and category (travel, meals, etc.)',
                    '5. Add purpose and project code if required',
                    '6. Submit for approval; track status in My Claims',
                ],
                'video_link' => null,
                'tags' => ['expense', 'reimbursement', 'finance', 'claim'],
            ],
        ];

        foreach ($entries as $entry) {
            AiKnowledgeBase::updateOrCreate(
                ['question_pattern' => $entry['question_pattern']],
                $entry
            );
        }
    }
}
