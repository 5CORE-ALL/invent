<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;
use Tests\TestCase;

class TaskPolicyDeletePermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TaskPolicy::resetFullAccessEmailCache();
    }

    private function user(string $email, string $name = 'Someone'): User
    {
        $user = new User;
        $user->email = $email;
        $user->name = $name;

        return $user;
    }

    private function task(string $assignor, bool $corrective = false, ?string $assignTo = null): Task
    {
        $task = new Task;
        $task->assignor = $assignor;
        $task->is_corrective_action = $corrective;
        $task->assign_to = $assignTo;

        return $task;
    }

    public function test_assignor_can_delete_when_stored_as_first_name(): void
    {
        $user = $this->user('priya@5core.com', 'Priya Sharma');

        $this->assertTrue(TaskPolicy::userIsAssignor($user, 'Priya'));
        $this->assertTrue(TaskPolicy::userIsAssignor($user, 'priya sharma'));
        $this->assertTrue(TaskPolicy::userIsAssignor($user, 'priya@5core.com'));
        $this->assertTrue(TaskPolicy::userCanDeleteTask($user, $this->task('Priya')));
        $this->assertTrue(TaskPolicy::userCanDeleteTask($user, $this->task('priya@5core.com')));
    }

    public function test_non_assignor_cannot_delete_someone_elses_task(): void
    {
        $user = $this->user('other@5core.com', 'Other Person');

        $this->assertFalse(TaskPolicy::userIsAssignor($user, 'Amarjit'));
        $this->assertFalse(TaskPolicy::userCanDeleteTask($user, $this->task('Amarjit')));
        $this->assertFalse(TaskPolicy::userCanDeleteTask($user, $this->task('president@5core.com')));
    }

    public function test_full_access_is_email_only_not_display_name(): void
    {
        TaskPolicy::resetFullAccessEmailCache();

        $namedJasmine = $this->user('random.person@5core.com', 'Jasmine');
        $this->assertFalse(TaskPolicy::userHasSpecialTaskPermission($namedJasmine));
        $this->assertFalse(TaskPolicy::userCanDeleteTask($namedJasmine, $this->task('someone@5core.com')));
    }

    public function test_listed_emails_have_full_task_access(): void
    {
        TaskPolicy::resetFullAccessEmailCache();

        $emails = [
            'president@5core.com',
            'sr.manager@5core.com',
            'inventory@5core.com',
            'ritu.kaur013@gmail.com',
            'sjoy7486@gmail.com',
            'sourcing@5core.com',
            'ineetkalra@5core.com',
            'priyanka@5core.com',
            'priyankakalra@5core.com',
            'software5@5core.com',
            'hr@5core.com',
        ];

        foreach ($emails as $email) {
            $user = $this->user($email, 'Other Name');
            $this->assertTrue(TaskPolicy::userHasSpecialTaskPermission($user), $email);
            if (in_array($email, ['president@5core.com', 'inventory@5core.com', 'ritu.kaur013@gmail.com', 'hr@5core.com'], true)) {
                $this->assertTrue(TaskPolicy::userCanDeleteAnyTask($user), $email);
                $this->assertTrue(TaskPolicy::userCanDeleteTask($user, $this->task('someone@5core.com')), $email);
                $this->assertTrue(TaskPolicy::userCanDeleteTask($user, $this->task('someone@5core.com', true)), $email);
                continue;
            }

            $this->assertFalse(TaskPolicy::userCanDeleteAnyTask($user), $email);
            $this->assertFalse(TaskPolicy::userCanDeleteTask($user, $this->task('someone@5core.com')), $email);
            $this->assertFalse(TaskPolicy::userCanDeleteTask($user, $this->task('someone@5core.com', true)), $email);
            $this->assertTrue(TaskPolicy::userCanDeleteTask($user, $this->task($email)), $email);
        }
    }

    public function test_seniors_cannot_delete_another_assignors_task(): void
    {
        TaskPolicy::resetFullAccessEmailCache();

        $seniors = [
            'sr.manager@5core.com',
            'sjoy7486@gmail.com',
            'ineetkalra@5core.com',
            'priyanka@5core.com',
            'software5@5core.com',
            'mgr-operations@5core.com',
        ];

        foreach ($seniors as $email) {
            $user = $this->user($email, 'Senior');
            $this->assertTrue(TaskPolicy::userHasFullTaskAccess($user), $email);
            $this->assertFalse(TaskPolicy::userCanDeleteTask($user, $this->task('other.assignor@5core.com')), $email);
            $this->assertFalse(TaskPolicy::userCanDeleteCorrectiveTasks($user), $email);
        }
    }

    public function test_hr_can_edit_and_delete_any_task_and_change_assignor(): void
    {
        TaskPolicy::resetFullAccessEmailCache();

        $hr = $this->user('hr@5core.com', 'HR');
        $someoneElses = $this->task('hritiksha@5core.com', false, 'shebha@5core.com');
        $corrective = $this->task('hritiksha@5core.com', true, 'shebha@5core.com');

        $this->assertTrue(TaskPolicy::userHasFullTaskAccess($hr));
        $this->assertTrue(TaskPolicy::userCanDeleteAnyTask($hr));
        $this->assertTrue(TaskPolicy::userCanDeleteTask($hr, $someoneElses));
        $this->assertTrue(TaskPolicy::userCanDeleteTask($hr, $corrective));
        $this->assertTrue(TaskPolicy::userCanChangeTaskAssignor($hr));
        $this->assertFalse(TaskPolicy::userCanChangeTaskAssignor($this->user('priya@5core.com', 'Priya Sharma')));
    }

    public function test_president_can_delete_any_task_including_corrective_action(): void
    {
        TaskPolicy::resetFullAccessEmailCache();

        $president = $this->user('president@5core.com', 'President');

        $this->assertTrue(TaskPolicy::userHasSpecialTaskPermission($president));
        $this->assertTrue(TaskPolicy::userCanDeleteTask($president, $this->task('someone@5core.com')));
        $this->assertTrue(TaskPolicy::userCanDeleteTask($president, $this->task('Amarjit')));
        $this->assertTrue(TaskPolicy::userCanDeleteTask($president, $this->task('someone@5core.com', true)));
    }

    public function test_name_needles_are_used_only_to_find_users_table_emails(): void
    {
        $this->assertTrue(TaskPolicy::userNameMatchesFullAccessNeedles('Jasmine'));
        $this->assertTrue(TaskPolicy::userNameMatchesFullAccessNeedles('Amarjit Singh'));
        $this->assertTrue(TaskPolicy::userNameMatchesFullAccessNeedles('Ineet Kalra'));
        $this->assertTrue(TaskPolicy::userNameMatchesFullAccessNeedles('Priyanka'));
        $this->assertTrue(TaskPolicy::userNameMatchesFullAccessNeedles('Priyanka Kalra'));
        $this->assertTrue(TaskPolicy::userNameMatchesFullAccessNeedles('Joy Huang'));
        $this->assertTrue(TaskPolicy::userNameMatchesFullAccessNeedles('Shobha N'));
        $this->assertFalse(TaskPolicy::userNameMatchesFullAccessNeedles('Priya Sharma'));
        $this->assertFalse(TaskPolicy::userNameMatchesFullAccessNeedles('Hritiksha Deb'));
    }

    public function test_assignor_cannot_delete_corrective_action_tasks(): void
    {
        $user = $this->user('priya@5core.com', 'Priya Sharma');

        $this->assertFalse(TaskPolicy::userCanDeleteTask($user, $this->task('Priya', true, 'shebha@5core.com')));
    }

    public function test_assignee_can_delete_own_corrective_action_task(): void
    {
        $assignee = $this->user('shebha@5core.com', 'Shebha');
        $other = $this->user('other@5core.com', 'Other Person');
        $task = $this->task('Hritiksha', true, 'shebha@5core.com, other.person@5core.com');

        $this->assertTrue(TaskPolicy::userCanDeleteTask($assignee, $task));
        $this->assertTrue(TaskPolicy::userCanDeleteTask($this->user('other.person@5core.com', 'Other Person'), $task));
        $this->assertFalse(TaskPolicy::userCanDeleteTask($other, $task));
        $this->assertFalse(TaskPolicy::userCanDeleteTask($assignee, $this->task('Hritiksha', false, 'shebha@5core.com')));
    }

    public function test_find_user_for_assignor_matches_name_or_email(): void
    {
        $priya = $this->user('priya@5core.com', 'Priya Sharma');
        $other = $this->user('other@5core.com', 'Other Person');
        $pool = [$priya, $other];

        $this->assertSame($priya, TaskPolicy::findUserForAssignorValue('Priya', $pool));
        $this->assertSame($priya, TaskPolicy::findUserForAssignorValue('priya@5core.com', $pool));
        $this->assertNull(TaskPolicy::findUserForAssignorValue('Unknown', $pool));
    }
}
