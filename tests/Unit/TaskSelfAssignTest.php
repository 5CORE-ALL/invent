<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\TaskSelfAssign;
use Tests\TestCase;

class TaskSelfAssignTest extends TestCase
{
    private function user(int $id, string $email, string $name): User
    {
        $user = new User;
        $user->id = $id;
        $user->email = $email;
        $user->name = $name;

        return $user;
    }

    public function test_same_email_is_self_assigned(): void
    {
        $this->assertTrue(TaskSelfAssign::isSamePerson('joy@5core.com', 'joy@5core.com'));
    }

    public function test_email_match_is_case_insensitive(): void
    {
        $this->assertTrue(TaskSelfAssign::isSamePerson('Joy@5core.com', ' joy@5core.com '));
    }

    public function test_different_people_are_not_self_assigned(): void
    {
        $this->assertFalse(TaskSelfAssign::isSamePerson('joy@5core.com', 'rita@5core.com'));
    }

    public function test_shared_task_is_not_self_assigned_when_others_are_included(): void
    {
        $this->assertFalse(TaskSelfAssign::isSamePerson(
            'joy@5core.com',
            'joy@5core.com, rita@5core.com'
        ));
    }

    public function test_duplicate_assignee_email_still_counts_as_one_person(): void
    {
        $this->assertTrue(TaskSelfAssign::isSamePerson(
            'joy@5core.com',
            'joy@5core.com, Joy@5core.com'
        ));
    }

    public function test_email_assignor_matches_assignee_stored_as_name(): void
    {
        $joy = $this->user(4, 'joy@5core.com', 'Joy Huang');

        $this->assertTrue(TaskSelfAssign::isSamePerson('joy@5core.com', 'Joy Huang', $joy, $joy));
    }

    public function test_first_name_assignor_matches_assignee_email(): void
    {
        $joy = $this->user(4, 'joy@5core.com', 'Joy Huang');

        $this->assertTrue(TaskSelfAssign::isSamePerson('Joy', 'joy@5core.com', null, $joy));
    }

    public function test_blank_assignor_or_assignee_is_not_self_assigned(): void
    {
        $this->assertFalse(TaskSelfAssign::isSamePerson('', 'joy@5core.com'));
        $this->assertFalse(TaskSelfAssign::isSamePerson('joy@5core.com', ''));
        $this->assertFalse(TaskSelfAssign::isSamePerson('joy@5core.com', null));
    }
}
