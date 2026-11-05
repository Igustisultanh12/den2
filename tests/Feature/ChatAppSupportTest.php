<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Super Admin dirahasiakan di Chat Dinas: tidak bisa dicari/di-chat oleh personel, komandan
 * maupun admin, dan hanya tampil sebagai "Layanan Aplikasi" yang berjalan dengan konfirmasi.
 */
class ChatAppSupportTest extends TestCase
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

    public function test_super_admin_tidak_muncul_di_kontak_dan_tidak_bisa_dichat_pribadi(): void
    {
        $sa = $this->user(['role' => 'super_admin', 'name' => 'Rahasia Sekali']);
        $admin = $this->user(['role' => 'admin', 'name' => 'Admin Satu']);
        $komandan = $this->user(['role' => 'komandan', 'name' => 'Danden']);
        $pers = $this->user(['name' => 'Pers Biasa']);

        foreach ([$admin, $komandan, $pers] as $viewer) {
            $ids = array_column($this->actingAs($viewer)->getJson('/live-chat/search-users')->assertOk()->json('users'), 'id');
            $this->assertNotContains($sa->id, $ids);
            $this->actingAs($viewer)->postJson('/live-chat/start', ['user_id' => $sa->id])->assertNotFound();
        }

        // Super Admin juga tidak membuka chat pribadi ke personel (identitasnya akan terlihat).
        $this->actingAs($sa)->postJson('/live-chat/start', ['user_id' => $pers->id])->assertStatus(422);
    }

    public function test_alur_layanan_aplikasi_menunggu_konfirmasi_dan_identitas_disamarkan(): void
    {
        $sa = $this->user(['role' => 'super_admin', 'name' => 'Rahasia Sekali', 'pangkat' => 'Kolonel']);
        $admin = $this->user(['role' => 'admin']);
        $pers = $this->user(['name' => 'Pers Biasa']);

        // Personel membuka Layanan Aplikasi dan mengirim permintaan.
        $uuid = $this->actingAs($pers)->postJson('/live-chat/start', ['app_support' => true])->assertOk()->json('uuid');
        $this->actingAs($pers)->postJson("/live-chat/{$uuid}/send", ['message' => 'Aplikasi error saat upload'])->assertOk();

        $thread = $this->actingAs($pers)->getJson("/live-chat/{$uuid}/messages")->assertOk()->json('thread');
        $this->assertSame('app', $thread['kind']);
        $this->assertSame('Layanan Aplikasi', $thread['title']);
        $this->assertTrue($thread['awaiting_confirmation']);
        $this->assertFalse($thread['can_send']);
        $this->assertFalse($thread['can_call']);

        // Pesan kedua ditolak sampai dikonfirmasi.
        $this->actingAs($pers)->postJson("/live-chat/{$uuid}/send", ['message' => 'Halo?'])->assertStatus(422);

        // Admin biasa tidak melihat / membuka utas Layanan Aplikasi.
        $adminList = $this->actingAs($admin)->getJson('/live-chat/sync')->assertOk()->json('conversations');
        $this->assertNotContains($uuid, array_column($adminList, 'uuid'));
        $this->actingAs($admin)->getJson("/live-chat/{$uuid}/messages")->assertForbidden();

        // Super Admin melihat permintaan di urutan teratas dan menerimanya.
        $saList = $this->actingAs($sa)->getJson('/live-chat/sync')->assertOk()->json('conversations');
        $this->assertSame($uuid, $saList[0]['uuid']);
        $this->assertTrue($saList[0]['can_respond']);
        $this->actingAs($sa)->postJson("/live-chat/{$uuid}/app-request", ['action' => 'accept'])->assertOk()->assertJson(['status' => 'OPEN']);
        $this->actingAs($sa)->postJson("/live-chat/{$uuid}/send", ['message' => 'Silakan kirim tangkapan layar'])->assertOk();

        // Personel bisa membalas, dan tidak pernah melihat nama/ID Super Admin.
        $this->actingAs($pers)->postJson("/live-chat/{$uuid}/send", ['message' => 'Baik'])->assertOk();
        $detail = $this->actingAs($pers)->getJson("/live-chat/{$uuid}/messages")->assertOk();
        $raw = $detail->getContent();
        $this->assertStringNotContainsString('Rahasia Sekali', $raw);
        $this->assertStringNotContainsString('Kolonel', $raw);
        $reply = collect($detail->json('messages'))->firstWhere('message', 'Silakan kirim tangkapan layar');
        $this->assertSame('Layanan Aplikasi', $reply['sender_name']);
        $this->assertNull($reply['sender_id']);

        $list = $this->actingAs($pers)->getJson('/live-chat/sync')->assertOk()->getContent();
        $this->assertStringNotContainsString('Rahasia Sekali', $list);

        // Panggilan tidak tersedia di Layanan Aplikasi.
        $this->actingAs($pers)->postJson("/live-chat/{$uuid}/call/initiate")->assertStatus(422);

        // Sesi diakhiri → pesan berikutnya menjadi permintaan baru (menunggu konfirmasi lagi).
        $this->actingAs($sa)->postJson("/live-chat/{$uuid}/status")->assertOk()->assertJson(['status' => 'CLOSED']);
        $this->actingAs($pers)->postJson("/live-chat/{$uuid}/send", ['message' => 'Ada kendala lain'])->assertOk();
        $again = $this->actingAs($pers)->getJson("/live-chat/{$uuid}/messages")->json('thread');
        $this->assertSame('PENDING', $again['status']);
        $this->assertTrue($again['awaiting_confirmation']);
    }

    public function test_super_admin_tidak_bisa_mengajukan_dan_permintaan_bisa_ditolak(): void
    {
        $sa = $this->user(['role' => 'super_admin']);
        $komandan = $this->user(['role' => 'komandan']);

        $this->actingAs($sa)->postJson('/live-chat/start', ['app_support' => true])->assertStatus(422);

        $uuid = $this->actingAs($komandan)->postJson('/live-chat/start', ['app_support' => true])->json('uuid');
        $this->actingAs($komandan)->postJson("/live-chat/{$uuid}/send", ['message' => 'Mohon bantuan'])->assertOk();
        // Hanya Super Admin yang dapat mengonfirmasi.
        $this->actingAs($komandan)->postJson("/live-chat/{$uuid}/app-request", ['action' => 'accept'])->assertForbidden();

        $this->actingAs($sa)->postJson("/live-chat/{$uuid}/app-request", ['action' => 'reject'])->assertOk()->assertJson(['status' => 'CLOSED']);
        $msgs = $this->actingAs($komandan)->getJson("/live-chat/{$uuid}/messages")->json('messages');
        $this->assertStringContainsString('ditolak', end($msgs)['message']);
    }

    public function test_balasan_super_admin_di_operator_dinas_juga_disamarkan(): void
    {
        $sa = $this->user(['role' => 'super_admin', 'name' => 'Rahasia Sekali']);
        $pers = $this->user();

        $uuid = $this->actingAs($pers)->postJson('/live-chat/start', ['support' => true])->json('uuid');
        $this->actingAs($pers)->postJson("/live-chat/{$uuid}/send", ['message' => 'Lapor'])->assertOk();
        $this->actingAs($sa)->postJson("/live-chat/{$uuid}/send", ['message' => 'Diterima'])->assertOk();
        // Super Admin tidak dapat menelepon personel dari utas layanan.
        $this->actingAs($sa)->postJson("/live-chat/{$uuid}/call/initiate")->assertStatus(422);

        $raw = $this->actingAs($pers)->getJson("/live-chat/{$uuid}/messages")->assertOk()->getContent();
        $this->assertStringNotContainsString('Rahasia Sekali', $raw);
    }

    public function test_api_mobile_layanan_aplikasi_dan_cek_sistem_khusus_super_admin(): void
    {
        $sa = $this->user(['role' => 'super_admin', 'name' => 'Rahasia Sekali']);
        $admin = $this->user(['role' => 'admin']);
        $pers = $this->user();
        $saToken = $sa->createToken('t')->plainTextToken;
        $persToken = $pers->createToken('t')->plainTextToken;
        $adminToken = $admin->createToken('t')->plainTextToken;

        // Layanan Aplikasi lewat API mobile.
        $uuid = $this->withToken($persToken)->postJson('/api/mobile/chat/start', ['app_support' => true])->assertOk()->json('uuid');
        $this->withToken($persToken)->postJson("/api/mobile/chat/{$uuid}/send", ['message' => 'Tolong'])->assertOk();
        $this->assertFalse($this->withToken($persToken)->getJson('/api/mobile/chat')->json('is_super_admin'));
        $this->withToken($persToken)->postJson("/api/mobile/chat/{$uuid}/app-request", ['action' => 'accept'])->assertForbidden();
        $this->withToken($saToken)->postJson("/api/mobile/chat/{$uuid}/app-request", ['action' => 'accept'])->assertOk();
        $this->withToken($saToken)->postJson("/api/mobile/chat/{$uuid}/send", ['message' => 'Siap'])->assertOk();
        $this->assertStringNotContainsString('Rahasia Sekali', $this->withToken($persToken)->getJson("/api/mobile/chat/{$uuid}/messages")->getContent());

        // Cek Sistem: hanya Super Admin (sama dengan web).
        $this->assertContains('system.view', \App\Support\MobileAccess::grantedAbilities($sa));
        $this->assertNotContains('system.view', \App\Support\MobileAccess::grantedAbilities($admin));
        $this->withToken($adminToken)->getJson('/api/mobile/system-check')->assertForbidden();
        $this->withToken($saToken)->getJson('/api/mobile/system-check')->assertOk()->assertJsonStructure(['cpu', 'ram', 'disk' => ['physical_disks', 'partitions'], 'server', 'database']);
    }

    public function test_halaman_chat_memberi_tahu_peran_super_admin(): void
    {
        $sa = $this->user(['role' => 'super_admin']);
        $pers = $this->user();

        $this->actingAs($sa)->get('/live-chat')->assertOk()->assertInertia(fn (Assert $p) => $p->where('isSuperAdmin', true));
        $this->actingAs($pers)->get('/live-chat')->assertOk()->assertInertia(fn (Assert $p) => $p->where('isSuperAdmin', false));
    }
}
