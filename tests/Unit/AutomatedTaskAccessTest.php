<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\TaskPolicy;
use App\Support\AutomatedTaskAccess;
use Tests\TestCase;

class AutomatedTaskAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TaskPolicy::resetFullAccessEmailCache();
    }

    private function user(string $email, string $name = 'Someone', ?string $role = null): User
    {
        $user = new User;
        $user->email = $email;
        $user->name = $name;
        $user->role = $role;

        return $user;
    }

    private function template(int $id, ?string $assignor, ?string $assignTo = null, ?int $parentId = null): object
    {
        return (object) [
            'id' => $id,
            'assignor' => $assignor,
            'assign_to' => $assignTo,
            'parent_task_id' => $parentId,
            'title' => 'Template '.$id,
        ];
    }

    public function test_assignor_can_view_modify_and_delete_their_own_template(): void
    {
        $user = $this->user('a@5core.com', 'User A');
        $own = $this->template(1, 'a@5core.com', 'b@5core.com');

        $this->assertTrue(TaskPolicy::userCanViewAutomatedTask($user, $own));
        $this->assertTrue(TaskPolicy::userCanModifyAutomatedTask($user, $own));
        $this->assertTrue(TaskPolicy::userCanDeleteAutomatedTask($user, $own));
    }

    public function test_user_cannot_view_modify_or_delete_someone_elses_template(): void
    {
        $user = $this->user('a@5core.com', 'User A');
        $theirs = $this->template(2, 'b@5core.com', 'c@5core.com');

        $this->assertFalse(TaskPolicy::userCanViewAutomatedTask($user, $theirs));
        $this->assertFalse(TaskPolicy::userCanModifyAutomatedTask($user, $theirs));
        $this->assertFalse(TaskPolicy::userCanDeleteAutomatedTask($user, $theirs));
    }

    public function test_assignee_can_view_but_cannot_modify_or_delete(): void
    {
        $user = $this->user('a@5core.com', 'User A');
        $assigned = $this->template(3, 'b@5core.com', 'a@5core.com, other@5core.com');

        $this->assertTrue(TaskPolicy::userCanViewAutomatedTask($user, $assigned));
        $this->assertFalse(TaskPolicy::userCanModifyAutomatedTask($user, $assigned));
        $this->assertFalse(TaskPolicy::userCanDeleteAutomatedTask($user, $assigned));
    }

    public function test_name_stored_assignor_still_owns_the_template(): void
    {
        $user = $this->user('priya@5core.com', 'Priya Sharma');
        $own = $this->template(4, 'Priya');

        $this->assertTrue(TaskPolicy::userCanViewAutomatedTask($user, $own));
        $this->assertTrue(TaskPolicy::userCanDeleteAutomatedTask($user, $own));
    }

    public function test_role_admin_can_view_modify_and_delete_any_template(): void
    {
        $admin = $this->user('admin.user@5core.com', 'Admin User', 'admin');
        $theirs = $this->template(5, 'b@5core.com', 'c@5core.com');

        $this->assertTrue(TaskPolicy::userCanViewAutomatedTask($admin, $theirs));
        $this->assertTrue(TaskPolicy::userCanModifyAutomatedTask($admin, $theirs));
        $this->assertTrue(TaskPolicy::userCanDeleteAutomatedTask($admin, $theirs));
    }

    public function test_full_access_senior_can_edit_but_not_delete_someone_elses_template(): void
    {
        $senior = $this->user('sr.manager@5core.com', 'Jasmine');
        $theirs = $this->template(6, 'b@5core.com', 'c@5core.com');

        $this->assertTrue(TaskPolicy::userHasFullTaskAccess($senior));
        $this->assertTrue(TaskPolicy::userCanViewAutomatedTask($senior, $theirs));
        $this->assertTrue(TaskPolicy::userCanModifyAutomatedTask($senior, $theirs));
        $this->assertFalse(TaskPolicy::userCanDeleteAutomatedTask($senior, $theirs));
        $this->assertTrue(TaskPolicy::userCanDeleteAutomatedTask($senior, $this->template(7, 'sr.manager@5core.com')));
    }

    public function test_president_can_delete_any_template(): void
    {
        $president = $this->user('president@5core.com', 'Amarjit Singh');
        $theirs = $this->template(8, 'b@5core.com');

        $this->assertTrue(TaskPolicy::userCanDeleteAutomatedTask($president, $theirs));
    }

    public function test_listing_hides_other_users_templates_and_keeps_owned_and_assigned(): void
    {
        $user = $this->user('a@5core.com', 'User A');
        $rows = [
            $this->template(1, 'a@5core.com', 'c@5core.com'),
            $this->template(2, 'b@5core.com', 'c@5core.com'),
            $this->template(3, 'b@5core.com', 'a@5core.com'),
            $this->template(4, 'b@5core.com', 'c@5core.com', 1),
            $this->template(5, 'b@5core.com', 'c@5core.com', 2),
        ];

        $visibleIds = AutomatedTaskAccess::filterVisible($user, $rows)->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($visibleIds);

        $this->assertSame([1, 3, 4], $visibleIds);
    }

    public function test_owner_of_parent_can_modify_and_delete_a_child_template(): void
    {
        $user = $this->user('a@5core.com', 'User A');
        $parent = $this->template(1, 'a@5core.com', 'c@5core.com');
        $child = $this->template(4, 'b@5core.com', 'c@5core.com', 1);
        $byId = [1 => $parent, 4 => $child];

        $this->assertTrue(AutomatedTaskAccess::canModify($user, $child, $byId));
        $this->assertTrue(AutomatedTaskAccess::canDelete($user, $child, $byId));
        $this->assertFalse(AutomatedTaskAccess::canDelete($user, $this->template(2, 'b@5core.com'), $byId));
    }

    public function test_admin_listing_includes_every_template(): void
    {
        $admin = $this->user('admin.user@5core.com', 'Admin User', 'admin');
        $rows = [
            $this->template(1, 'a@5core.com'),
            $this->template(2, 'b@5core.com'),
        ];

        $this->assertCount(2, AutomatedTaskAccess::filterVisible($admin, $rows));
    }
}
