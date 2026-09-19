<?php

namespace Tests\Feature;

use App\Support\ChatWorkspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ChatMobileDeepLinkTest extends TestCase
{
    use DatabaseTransactions;

    public function test_chat_index_accepts_channel_and_message_query(): void
    {
        if (! ChatWorkspace::tablesReady()) {
            $this->markTestSkipped('Chat tables are not ready.');
        }

        $this->withoutMiddleware(\App\Http\Middleware\CloseDbConnections::class);

        $user = ChatWorkspace::activeUsersQuery()
            ->where(function ($q) {
                $q->where('logined', 1)->orWhere('stay_logged_in', 1);
            })
            ->orderBy('id')
            ->first();
        if (! $user) {
            $this->markTestSkipped('Need an active logged-in user.');
        }

        $this->actingAs($user)
            ->get('/chat?channel=1&message=1')
            ->assertOk()
            ->assertSee('Invent Chat', false)
            ->assertSee('slackCameraBtn', false)
            ->assertSee('/chat?channel=', false);
    }
}
