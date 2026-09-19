<?php

namespace Tests\Feature;

use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\User;
use App\Support\ChatWorkspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatProductionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\CloseDbConnections::class);
        if (! ChatWorkspace::tablesReady()) {
            $this->markTestSkipped('Chat tables are not ready.');
        }
    }

    /**
     * @return array{0: User, 1: User, 2: ChatChannel}
     */
    private function dmPair(): array
    {
        $loggedIn = function ($q) {
            $q->where('logined', 1)->orWhere('stay_logged_in', 1);
        };
        $users = ChatWorkspace::activeUsersQuery()
            ->where($loggedIn)
            ->where(function ($q) {
                $q->whereNull('role')->orWhereNotIn('role', ['admin', 'superadmin', 'manager']);
            })
            ->where(function ($q) {
                $q->whereNull('org_level')->orWhereRaw('LOWER(org_level) <> ?', ['director']);
            })
            ->whereRaw('LOWER(email) NOT IN (?, ?)', ['software5@5core.com', 'president@5core.com'])
            ->orderBy('id')
            ->limit(3)
            ->get();
        if ($users->count() < 2) {
            $users = ChatWorkspace::activeUsersQuery()->where($loggedIn)->orderBy('id')->limit(3)->get();
        }
        if ($users->count() < 2) {
            $this->markTestSkipped('Need two active users for chat tests.');
        }
        $a = $users[0];
        $b = $users[1];
        $channel = ChatWorkspace::dmBetween($a, $b);

        return [$a, $b, $channel];
    }

    public function test_send_message(): void
    {
        [$a, $b, $channel] = $this->dmPair();

        $res = $this->actingAs($a)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'hello reliability',
            'client_id' => 'cid-send-'.uniqid(),
        ]);

        $res->assertOk()->assertJsonPath('messages.0.body', 'hello reliability');
        $this->assertGreaterThan(0, (int) $res->json('messages.0.id'));
        $this->assertSame('hello reliability', ChatMessage::query()->find($res->json('messages.0.id'))?->body);
    }

    public function test_duplicate_send_prevention(): void
    {
        [$a, $b, $channel] = $this->dmPair();
        $payload = ['body' => 'once only', 'client_id' => 'cid-dup-'.uniqid()];

        $this->actingAs($a)->postJson('/chat/channels/'.$channel->id.'/messages', $payload)->assertOk();
        $again = $this->actingAs($a)->postJson('/chat/channels/'.$channel->id.'/messages', $payload);

        $again->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(1, ChatMessage::query()->where('client_id', $payload['client_id'])->count());
    }

    public function test_failed_message_validation(): void
    {
        [$a, $b, $channel] = $this->dmPair();

        $this->actingAs($a)
            ->postJson('/chat/channels/'.$channel->id.'/messages', ['body' => ''])
            ->assertStatus(422);
    }

    public function test_retry_uses_same_client_id(): void
    {
        [$a, $b, $channel] = $this->dmPair();
        $cid = 'cid-retry-1';

        $first = $this->actingAs($a)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'retry me',
            'client_id' => $cid,
        ]);
        $first->assertOk();
        $id = $first->json('messages.0.id');

        $retry = $this->actingAs($a)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'retry me',
            'client_id' => $cid,
        ]);
        $retry->assertOk()->assertJsonPath('messages.0.id', $id);
    }

    public function test_missed_messages_after_reconnect_without_duplicates(): void
    {
        [$a, $b, $channel] = $this->dmPair();
        $this->actingAs($a)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'first',
            'client_id' => 'cid-miss-1',
        ])->assertOk();

        $after = (int) ChatMessage::query()->where('channel_id', $channel->id)->max('id');

        $this->actingAs($b)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'while you were gone',
            'client_id' => 'cid-miss-2',
        ])->assertOk();

        $sync = $this->actingAs($a)->getJson('/chat/sync?channel='.$channel->id.'&after='.$after);
        $sync->assertOk();
        $ids = collect($sync->json('messages'))->pluck('id');
        $this->assertCount(1, $ids);

        $again = $this->actingAs($a)->getJson('/chat/sync?channel='.$channel->id.'&after='.$after);
        $this->assertEquals($ids->all(), collect($again->json('messages'))->pluck('id')->all());
    }

    public function test_unread_counts_and_mark_read(): void
    {
        [$a, $b, $channel] = $this->dmPair();
        $this->actingAs($b)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'unread ping',
            'client_id' => 'cid-un-1',
        ])->assertOk();

        $inbox = $this->actingAs($a)->getJson('/chat/inbox');
        $inbox->assertOk();
        $row = collect($inbox->json('channels'))->firstWhere('id', $channel->id);
        $this->assertGreaterThan(0, (int) ($row['unread'] ?? 0));

        $this->actingAs($a)->postJson('/chat/channels/'.$channel->id.'/read')->assertOk();
        $after = $this->actingAs($a)->getJson('/chat/inbox');
        $row2 = collect($after->json('channels'))->firstWhere('id', $channel->id);
        $this->assertSame(0, (int) ($row2['unread'] ?? 0));
    }

    public function test_mark_all_read(): void
    {
        [$a, $b, $channel] = $this->dmPair();
        $this->actingAs($b)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'another unread',
            'client_id' => 'cid-un-all',
        ])->assertOk();

        $this->actingAs($a)->postJson('/chat/read-all')->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_mentions_resolve_and_block_everyone_for_regular_users(): void
    {
        [$a, $b, $channel] = $this->dmPair();
        $token = '@'.preg_replace('/\s+/', '', strtolower((string) explode(' ', $b->name)[0]));

        $ok = $this->actingAs($a)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'hey '.$token,
            'client_id' => 'cid-men-1',
        ]);
        $ok->assertOk();

        $this->actingAs($a)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'ping @everyone',
            'client_id' => 'cid-men-2',
        ])->assertStatus(403);
    }

    public function test_notifications_skip_author(): void
    {
        [$a, $b, $channel] = $this->dmPair();
        $this->actingAs($a)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'notify peer',
            'client_id' => 'cid-nt-1',
        ])->assertOk();

        $authorSync = $this->actingAs($a)->getJson('/chat/sync?channel='.$channel->id.'&after=0');
        $alerts = collect($authorSync->json('alerts') ?? []);
        $this->assertTrue($alerts->where('channel_id', $channel->id)->isEmpty());
    }

    public function test_threads(): void
    {
        [$a, $b, $channel] = $this->dmPair();
        $parent = $this->actingAs($a)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'parent thread',
            'client_id' => 'cid-th-1',
        ]);
        $parent->assertOk();
        $parentId = (int) $parent->json('messages.0.id');

        $this->actingAs($b)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'reply in thread',
            'client_id' => 'cid-th-2',
            'parent_id' => $parentId,
        ])->assertOk();

        $thread = $this->actingAs($a)->getJson('/chat/channels/'.$channel->id.'/messages?parent_id='.$parentId);
        $thread->assertOk();
        $this->assertGreaterThanOrEqual(2, count($thread->json('messages')));
    }

    public function test_permissions_regular_user_cannot_create_channel_or_pin(): void
    {
        [$a, $b, $channel] = $this->dmPair();
        if (ChatWorkspace::canManageChannels($a)) {
            $this->markTestSkipped('No non-admin user available to assert channel permissions.');
        }
        $msg = $this->actingAs($a)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'pin me',
            'client_id' => 'cid-perm-1',
        ]);
        $msg->assertOk();

        $this->actingAs($a)->postJson('/chat/channels', [
            'name' => 'should-fail-'.uniqid(),
            'type' => 'public',
        ])->assertStatus(403);

        $this->actingAs($a)->postJson('/chat/messages/'.$msg->json('messages.0.id').'/pin')->assertStatus(403);
    }

    public function test_attachments_are_member_gated(): void
    {
        Storage::fake('local');
        [$a, $b, $channel] = $this->dmPair();
        $outsider = ChatWorkspace::activeUsersQuery()->whereNotIn('id', [$a->id, $b->id])->orderBy('id')->first();
        if (! $outsider) {
            $this->markTestSkipped('Need a third active user for attachment ACL.');
        }

        $res = $this->actingAs($a)->post('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'file',
            'client_id' => 'cid-file-1',
            'file' => UploadedFile::fake()->image('shot.jpg'),
        ]);
        $res->assertOk();
        $id = (int) $res->json('messages.0.id');

        $this->actingAs($a)->get('/chat/files/'.$id)->assertOk();
        $this->actingAs($outsider)->get('/chat/files/'.$id)->assertStatus(403);
    }

    public function test_message_to_task_stores_reference(): void
    {
        [$a, $b, $channel] = $this->dmPair();
        $msg = $this->actingAs($a)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => 'Buy packing tape from chat',
            'client_id' => 'cid-task-1',
        ]);
        $msg->assertOk();
        $mid = (int) $msg->json('messages.0.id');

        $task = $this->actingAs($a)->postJson('/chat/messages/'.$mid.'/task', [
            'assign_user_id' => $a->id,
        ]);
        $task->assertOk()->assertJsonStructure(['task_id', 'task_url']);
        $this->assertNotNull(ChatMessage::query()->find($mid)?->task_id);
    }

    public function test_invent_bot_help_command(): void
    {
        [$a, $b, $channel] = $this->dmPair();
        $res = $this->actingAs($a)->postJson('/chat/channels/'.$channel->id.'/messages', [
            'body' => '/help',
            'client_id' => 'cid-bot-1',
        ]);
        $res->assertOk();
        $bodies = collect($res->json('messages'))->pluck('body')->implode(' ');
        $this->assertStringContainsString('Complete task', $bodies);
    }
}
