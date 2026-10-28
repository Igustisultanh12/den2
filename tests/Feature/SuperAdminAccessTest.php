<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Super Admin harus memiliki seluruh hak Admin plus modul khusus Super Admin. */
class SuperAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true, 'must_change_password' => false, 'mfa_enabled' => false]);
    }

    public function test_system_check_is_open_for_super_admin_only(): void
    {
        $this->actingAs($this->user('super_admin'))->get('/admin/system-check')->assertOk();
        $this->actingAs($this->user('admin'))->get('/admin/system-check')->assertForbidden();
        $this->actingAs($this->user('personel'))->get('/admin/system-check')->assertForbidden();
    }

    public function test_super_admin_counts_as_admin_in_model_checks(): void
    {
        $super = $this->user('super_admin');
        $this->assertTrue($super->isAdmin());
        $this->assertTrue($super->isPengurusKoperasi());
        $this->assertFalse($this->user('personel')->isAdmin());
    }

    public function test_wa_status_reports_gateway_down_clearly(): void
    {
        // Gateway mati → pesan jelas, bukan error 500.
        \Illuminate\Support\Facades\Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('refused'));
        $this->actingAs($this->user('super_admin'))->getJson(route('settings.wa-status'))
            ->assertOk()->assertJsonPath('gateway', 'down')->assertJsonPath('status', 'OFFLINE');
    }

    public function test_wa_status_starts_session_for_qr(): void
    {
        $super = $this->user('super_admin');

        // Gateway hidup tanpa sesi (instalasi baru) → Laravel meminta sesi "dinas" dibuat agar QR muncul.
        \Illuminate\Support\Facades\Http::fake([
            '127.0.0.1:3000/status' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'status' => 'OFFLINE', 'qr' => null, 'sessions' => []]),
            '127.0.0.1:3000/sessions' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'status' => 'CONNECTING']),
        ]);
        $this->actingAs($super)->getJson(route('settings.wa-status'))->assertOk()->assertJsonPath('gateway', 'up');
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sessions') && $r['sessionId'] === 'dinas');

        // Admin biasa tidak boleh mengakses status maupun logout WA.
        $this->actingAs($this->user('admin'))->getJson(route('settings.wa-status'))->assertForbidden();
        $this->actingAs($this->user('admin'))->postJson(route('settings.wa-logout'))->assertForbidden();
    }

    public function test_wa_logout_calls_gateway_for_super_admin(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '127.0.0.1:3000/sessions/dinas/logout' => \Illuminate\Support\Facades\Http::response(['ok' => true]),
        ]);
        $this->actingAs($this->user('super_admin'))->postJson(route('settings.wa-logout'))
            ->assertOk()->assertJsonPath('ok', true);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sessions/dinas/logout') && $r->method() === 'POST');
    }

    public function test_settings_save_without_copyright_field(): void
    {
        Setting::updateOrCreate(['key' => 'copyright'], ['value' => 'lama']);

        $this->actingAs($this->user('super_admin'))
            ->put(route('settings.update'), ['agency_name' => 'DETASEMEN INTELIJEN KOARMADA 2', 'start_number' => 1, 'copyright' => 'diubah'])
            ->assertSessionHasNoErrors();

        $this->assertSame('DETASEMEN INTELIJEN KOARMADA 2', Setting::where('key', 'agency_name')->value('value'));
        $this->assertSame('lama', Setting::where('key', 'copyright')->value('value'), 'copyright tidak lagi dapat diubah dari form');
    }
}
