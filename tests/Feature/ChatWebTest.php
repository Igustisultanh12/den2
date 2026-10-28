<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Chat Dinas web: chat pribadi antar-personel memakai migrasi asli (kolom peer_id). */
class ChatWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => 'personel', 'is_active' => true, 'must_change_password' => false, 'mfa_enabled' => false], $attrs));
    }

    public function test_every_personel_gets_the_conversation_page_and_can_chat_with_the_commander(): void
    {
        $pers = $this->user(['name' => 'Pers Web']);
        $komandan = $this->user(['role' => 'komandan', 'name' => 'Danden']);

        $this->actingAs($pers)->get('/live-chat')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Chat/Index')
                ->where('isOperator', false)
                ->where('conversations.0.kind', 'support'));

        $contacts = $this->actingAs($pers)->getJson('/live-chat/search-users?q=Danden')->assertOk()->json('users');
        $this->assertSame([$komandan->id], array_column($contacts, 'id'));

        $uuid = $this->actingAs($pers)->postJson('/live-chat/start', ['user_id' => $komandan->id])->assertOk()->json('uuid');
        $this->actingAs($pers)->postJson("/live-chat/{$uuid}/send", ['message' => 'Izin Komandan'])->assertOk();

        $sync = $this->actingAs($komandan)->getJson("/live-chat/sync?uuid={$uuid}")->assertOk();
        $this->assertSame(['Izin Komandan'], array_column($sync->json('active.messages'), 'message'));
        $this->assertSame('direct', $sync->json('active.thread.kind'));
    }

    public function test_attachment_download_is_limited_to_conversation_participants(): void
    {
        Storage::fake('public');
        $a = $this->user();
        $b = $this->user();
        $outsider = $this->user();
        $admin = $this->user(['role' => 'admin']);

        $uuid = $this->actingAs($a)->postJson('/live-chat/start', ['user_id' => $b->id])->json('uuid');
        $path = $this->actingAs($a)->post("/live-chat/{$uuid}/send", ['attachments' => [UploadedFile::fake()->image('foto.png', 20, 20)]], ['Accept' => 'application/json'])
            ->assertOk()->json('message.attachments.0.file_path');

        $this->actingAs($b)->get('/live-chat/attachment/download?path=' . urlencode($path))->assertOk();
        $this->actingAs($outsider)->get('/live-chat/attachment/download?path=' . urlencode($path))->assertForbidden();
        $this->actingAs($admin)->get('/live-chat/attachment/download?path=' . urlencode($path))->assertForbidden();
        $this->actingAs($a)->get('/live-chat/attachment/download?path=' . urlencode('../.env'))->assertNotFound();
    }
}
