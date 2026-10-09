<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\DriveItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriveSmokeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_drive_end_to_end(): void
    {
        $startId = (int) DriveItem::max('id');
        try {
            $this->runScenario();
        } finally {
            $ids = DriveItem::where('id', '>', $startId)->pluck('id');
            \Illuminate\Support\Facades\DB::table('drive_shares')->whereIn('item_id', $ids)->delete();
            \Illuminate\Support\Facades\DB::table('drive_stars')->whereIn('item_id', $ids)->delete();
            \Illuminate\Support\Facades\DB::table('drive_item_versions')->whereIn('item_id', $ids)->delete();
            \Illuminate\Support\Facades\DB::table('drive_activities')->whereIn('item_id', $ids)->delete();
            DriveItem::whereIn('id', $ids)->delete();
        }
    }

    private function runScenario(): void
    {
        Storage::fake('local');
        $this->withoutMiddleware(VerifyCsrfToken::class);
        [$owner, $other] = User::query()->whereNotNull('email')->orderBy('id')->take(2)->get()->all();
        $owner->logined = 1;
        $other->logined = 1;

        $this->actingAs($owner)->get('/drive')->assertOk()->assertSee('5Core Drive');

        $folder = $this->postJson('/drive/api/folders', ['name' => 'Listings'])->assertOk()->json('item');
        $this->assertTrue($folder['is_owner']);

        // Two-chunk upload into the folder.
        $uid = 'test-upload-0001';
        foreach (['hello ', 'world'] as $i => $part) {
            $res = $this->post('/drive/api/upload', [
                'upload_id' => $uid, 'chunk_index' => $i, 'total_chunks' => 2,
                'name' => 'note.txt', 'relative_path' => 'note.txt', 'parent' => $folder['id'], 'conflict' => 'keep',
                'chunk' => UploadedFile::fake()->createWithContent('chunk', $part),
            ], ['Accept' => 'application/json'])->assertOk();
        }
        $file = $res->json('item');
        $this->assertSame(11, $file['size']);
        $this->get('/drive/file/'.$file['id'].'?download=1')->assertOk();

        // Replace = new version, same item.
        $this->post('/drive/api/upload', [
            'upload_id' => 'test-upload-0002', 'chunk_index' => 0, 'total_chunks' => 1,
            'name' => 'note.txt', 'relative_path' => 'note.txt', 'parent' => $folder['id'], 'conflict' => 'replace',
            'chunk' => UploadedFile::fake()->createWithContent('chunk', 'v2'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('item.id', $file['id']);
        $this->getJson('/drive/api/items/'.$file['id'])->assertOk()->assertJsonCount(1, 'versions');

        // Folder upload creates nested folders.
        $this->post('/drive/api/upload', [
            'upload_id' => 'test-upload-0003', 'chunk_index' => 0, 'total_chunks' => 1,
            'name' => 'a.jpg', 'relative_path' => 'Pics/Red/a.jpg', 'parent' => $folder['id'], 'conflict' => 'keep',
            'chunk' => UploadedFile::fake()->image('a.jpg'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('item.category', 'image');
        $names = collect($this->getJson('/drive/api/list?folder='.$folder['id'])->json('items'))->pluck('name')->all();
        $this->assertContains('Pics', $names);

        // Text file create + edit.
        $txt = $this->postJson('/drive/api/text-files', ['name' => 'Doc', 'content' => 'abc'])->assertOk()->json('item');
        $this->assertSame('Doc.txt', $txt['name']);
        $this->postJson('/drive/api/items/'.$txt['id'].'/content', ['content' => 'xyz'])->assertOk();
        $this->getJson('/drive/api/items/'.$txt['id'].'/content')->assertJsonPath('content', 'xyz');

        // Share folder with other user as editor.
        $this->postJson('/drive/api/items/'.$folder['id'].'/share', ['emails' => [$other->email], 'role' => 'editor', 'notify' => false])->assertOk();

        // Public link + direct URL.
        $link = $this->postJson('/drive/api/items/'.$file['id'].'/link', ['access' => 'view'])->assertOk()->json();
        $this->assertNotEmpty($link['direct_url']);

        // Move text file into folder, star, copy, zip.
        $this->postJson('/drive/api/items/move', ['ids' => [$txt['id']], 'target' => $folder['id']])->assertOk()->assertJsonPath('moved', 1);
        $this->postJson('/drive/api/items/star', ['ids' => [$txt['id']], 'starred' => true])->assertOk();
        $this->assertCount(1, $this->getJson('/drive/api/list?view=starred')->json('items'));
        $this->postJson('/drive/api/items/copy', ['ids' => [$txt['id']]])->assertOk()->assertJsonPath('copied', 1);
        $this->get('/drive/zip?ids[]='.$folder['id'])->assertOk();
        $this->getJson('/drive/api/stats')->assertOk();
        $this->getJson('/drive/api/list?view=search&q=note')->assertOk();

        // --- As the editor ---
        $this->actingAs($other);
        $shared = collect($this->getJson('/drive/api/list?view=shared')->json('items'))->pluck('id')->all();
        $this->assertContains($folder['id'], $shared);
        $inside = collect($this->getJson('/drive/api/list?folder='.$folder['id'])->json('items'));
        $this->assertSame('editor', $inside->firstWhere('id', $file['id'])['role']);
        $this->postJson('/drive/api/items/'.$file['id'].'/rename', ['name' => 'renamed.txt'])->assertOk();
        $this->postJson('/drive/api/folders', ['name' => 'Editor sub', 'parent' => $folder['id']])->assertOk()->assertJsonPath('item.owner.id', $owner->id);
        // Editors cannot delete, remove access, or turn off link.
        $this->postJson('/drive/api/items/trash', ['ids' => [$file['id']]])->assertOk()->assertJsonPath('trashed', 0);
        $shareId = DriveItem::where('uuid', $folder['id'])->first()->shares()->first()->id;
        $this->postJson('/drive/api/items/'.$folder['id'].'/share/'.$shareId.'/remove')->assertForbidden();
        $this->postJson('/drive/api/items/'.$file['id'].'/link', ['access' => 'none'])->assertForbidden();

        // --- Public, logged out ---
        auth()->logout();
        $token = DriveItem::where('uuid', $file['id'])->value('share_token');
        $this->get('/drive/s/'.$token)->assertOk();
        $this->get('/drive/s/'.$token.'/raw/renamed.txt')->assertOk();

        // --- Owner trash / restore / delete forever ---
        $this->actingAs($owner);
        $this->postJson('/drive/api/items/trash', ['ids' => [$folder['id']]])->assertOk()->assertJsonPath('trashed', 1);
        $this->assertCount(1, $this->getJson('/drive/api/list?view=trash')->json('items'));
        $this->get('/drive/s/'.$token)->assertNotFound();
        $this->postJson('/drive/api/items/restore', ['ids' => [$folder['id']]])->assertOk();
        $this->get('/drive/s/'.$token)->assertOk();
        $this->postJson('/drive/api/items/trash', ['ids' => [$folder['id']]])->assertOk();
        $this->postJson('/drive/api/items/delete', ['ids' => [$folder['id']]])->assertOk();
        $this->assertSame(0, DriveItem::where('uuid', $file['id'])->count());
    }
}
