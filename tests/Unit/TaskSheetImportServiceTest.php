<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\TaskSheetImportService;
use App\Services\TaskWhatsAppNotificationService;
use Tests\TestCase;

class TaskSheetImportServiceTest extends TestCase
{
    private function service(): TaskSheetImportService
    {
        $whatsApp = $this->createMock(TaskWhatsAppNotificationService::class);

        return new TaskSheetImportService($whatsApp);
    }

    private function user(string $name, string $email): User
    {
        $user = new User;
        $user->name = $name;
        $user->email = $email;

        return $user;
    }

    public function test_header_rows_map_assignees_by_name_and_email(): void
    {
        $service = $this->service();
        $actor = $this->user('Admin User', 'admin@5core.com');
        $users = collect([
            $actor,
            $this->user('Jane Smith', 'jane@5core.com'),
            $this->user('John Doe', 'john@5core.com'),
        ]);

        $rows = $service->mapSpreadsheetRows([
            ['Task', 'Assignee', 'Assignor', 'Group', 'Priority', 'ETC Minutes'],
            ['Review listings', 'Jane Smith', 'John Doe', 'Marketplaces', 'Urgent', '45'],
            ['Fix titles', 'john@5core.com, jane@5core.com', 'Admin User', 'Development', 'low', '20'],
        ]);

        $first = $service->mapRowToTaskData($rows[0], $actor, true, $users);
        $this->assertSame('Review listings', $first['title']);
        $this->assertSame('jane@5core.com', $first['assign_to']);
        $this->assertSame('john@5core.com', $first['assignor']);
        $this->assertSame('high', $first['priority']);
        $this->assertSame(45, $first['eta_time']);
        $this->assertSame('manual', $first['task_type']);
        $this->assertSame(0, $first['is_automate_task']);
        $this->assertSame('Marketplaces', $first['group']);

        $second = $service->mapRowToTaskData($rows[1], $actor, true, $users);
        $this->assertSame('john@5core.com, jane@5core.com', $second['assign_to']);
        $this->assertSame('low', $second['priority']);
    }

    public function test_non_admin_uploader_is_the_assignor(): void
    {
        $service = $this->service();
        $actor = $this->user('Uploader', 'uploader@5core.com');
        $users = collect([
            $actor,
            $this->user('Jane Smith', 'jane@5core.com'),
            $this->user('John Doe', 'john@5core.com'),
        ]);

        $payload = $service->mapRowToTaskData([
            'title' => 'Check inventory',
            'assignee' => 'Jane Smith',
            'assignor' => 'John Doe',
        ], $actor, false, $users);

        $this->assertSame('uploader@5core.com', $payload['assignor']);
        $this->assertSame('jane@5core.com', $payload['assign_to']);
    }

    public function test_old_positional_template_still_maps(): void
    {
        $service = $this->service();
        $rows = $service->mapSpreadsheetRows([
            ['Marketplaces', 'Sample Task 1', 'John Doe', 'Jane Smith', 'Todo', 'Normal', '', 'L1: https://example.com'],
        ]);

        $this->assertSame('Sample Task 1', $rows[0]['title']);
        $this->assertSame('Jane Smith', $rows[0]['assignee']);
        $this->assertSame('John Doe', $rows[0]['assignor']);
        $this->assertSame('Marketplaces', $rows[0]['group']);
        $this->assertSame('L1: https://example.com', $rows[0]['links']);
    }

    public function test_missing_assignee_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Assignee is required.');

        $service = $this->service();
        $actor = $this->user('Admin User', 'admin@5core.com');
        $service->mapRowToTaskData(['title' => 'No owner'], $actor, true, collect([$actor]));
    }

    public function test_unknown_assignee_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Assignee not found');

        $service = $this->service();
        $actor = $this->user('Admin User', 'admin@5core.com');
        $service->mapRowToTaskData([
            'title' => 'Unknown person',
            'assignee' => 'Nobody Here',
        ], $actor, true, collect([$actor]));
    }

    public function test_priority_and_status_aliases(): void
    {
        $service = $this->service();

        $this->assertSame('high', $service->mapPriority('Urgent'));
        $this->assertSame('low', $service->mapPriority('LOW'));
        $this->assertSame('normal', $service->mapPriority(null));
        $this->assertSame('Need Help', $service->mapStatus('need help'));
        $this->assertSame('Monitor', $service->mapStatus('monitor'));
        $this->assertSame('Todo', $service->mapStatus('pending'));
        $this->assertSame('Todo', $service->mapStatus(null));
    }

    public function test_split_people_supports_commas_and_semicolons(): void
    {
        $service = $this->service();

        $this->assertSame(
            ['Jane Smith', 'john@5core.com'],
            $service->splitPeople('Jane Smith, john@5core.com; ')
        );
    }

    public function test_assignee_dropdown_columns_are_combined(): void
    {
        $service = $this->service();
        $actor = $this->user('Admin User', 'admin@5core.com');
        $users = collect([
            $actor,
            $this->user('Jane Smith', 'jane@5core.com'),
            $this->user('John Doe', 'john@5core.com'),
        ]);

        $rows = $service->mapSpreadsheetRows([
            ['Task', 'Assignee', 'Assignee 2', 'Assignee 3', 'Assignor'],
            ['Review listings', 'Jane Smith', 'John Doe', '', 'Admin User'],
        ]);

        $this->assertSame('Jane Smith', $rows[0]['assignee']);
        $this->assertSame('John Doe', $rows[0]['assignee_2']);
        $this->assertSame('Jane Smith, John Doe', $service->combinedAssigneeRaw($rows[0]));

        $payload = $service->mapRowToTaskData($rows[0], $actor, true, $users);
        $this->assertSame('jane@5core.com, john@5core.com', $payload['assign_to']);
        $this->assertSame('admin@5core.com', $payload['assignor']);
    }
}
