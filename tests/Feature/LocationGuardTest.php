<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PersonnelLocation;
use App\Models\User;
use App\Support\PersonnelTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Keamanan lokasi: personel tanpa GPS aktif tidak dapat login, dan aplikasi
 * lokasi palsu (Fake GPS) terdeteksi, ditolak, serta dilaporkan ke Admin.
 */
class LocationGuardTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_APP = 'Sinden2-Android/1.8.1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::fake();
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role, 'is_active' => true, 'must_change_password' => false, 'mfa_enabled' => false,
            'nrp' => 'NRP' . random_int(1000, 999999),
        ], $attrs));
    }

    private function gps(array $over = []): array
    {
        return array_merge(['latitude' => -7.2575, 'longitude' => 112.7521, 'accuracy' => 12], $over);
    }

    public function test_web_login_requires_gps_for_personel_but_not_admin(): void
    {
        $pers = $this->user('personel');

        // Tanpa lokasi: ditolak & tidak login.
        $this->post('/login', ['email' => $pers->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Koordinat 0,0 (tidak valid) juga ditolak.
        $this->post('/login', ['email' => $pers->email, 'password' => 'password', 'latitude' => 0, 'longitude' => 0])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Dengan GPS: berhasil & langsung tampil di Pantau Personel.
        $this->post('/login', ['email' => $pers->email, 'password' => 'password'] + $this->gps());
        $this->assertAuthenticatedAs($pers);
        $this->assertDatabaseHas('personnel_locations', ['user_id' => $pers->id, 'provider' => 'web-login']);
        $this->post('/logout');

        // Admin tetap dapat login tanpa GPS (mis. dari PC kantor).
        $admin = $this->user('admin');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_web_login_with_mocked_location_is_rejected_and_reported(): void
    {
        $pers = $this->user('personel');

        $this->post('/login', ['email' => $pers->email, 'password' => 'password', 'location_mocked' => true] + $this->gps())
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', ['action' => 'LOKASI_PALSU', 'target_personnel' => $pers->name]);
        $this->assertDatabaseHas('app_notifications', ['role' => 'admin', 'title' => 'Peringatan: Lokasi Palsu Terdeteksi']);
    }

    public function test_mobile_login_requires_gps_and_rejects_fake_gps_for_new_app(): void
    {
        $pers = $this->user('personel');
        $login = fn (array $extra = [], string $ua = self::NEW_APP) => $this->withHeaders(['User-Agent' => $ua])
            ->postJson('/api/mobile/login', array_merge(['username' => $pers->nrp, 'password' => 'password'], $extra));

        // Aplikasi 1.8.1 tanpa lokasi / GPS mati: ditolak.
        $login()->assertStatus(403)->assertJsonPath('code', 'location_required');
        $login(['location' => ['gps_enabled' => false]])->assertStatus(403)->assertJsonPath('code', 'location_required');

        // Fake GPS: ditolak & dilaporkan.
        $login(['location' => $this->gps(['is_mock' => true, 'gps_enabled' => true])])
            ->assertStatus(403)->assertJsonPath('code', 'mock_location');
        $this->assertSame(1, AuditLog::where('action', 'LOKASI_PALSU')->count());

        // GPS asli: berhasil & titik login tersimpan.
        $login(['location' => $this->gps(['gps_enabled' => true])])->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame('login', PersonnelLocation::where('user_id', $pers->id)->value('provider'));

        // Aplikasi versi lama (tanpa data lokasi) tetap boleh login agar dapat diperbarui.
        $login([], 'Sinden2-Android/1.8.0')->assertOk();

        // Admin dikecualikan.
        $admin = $this->user('admin');
        $this->withHeaders(['User-Agent' => self::NEW_APP])
            ->postJson('/api/mobile/login', ['username' => $admin->nrp, 'password' => 'password'])->assertOk();
    }

    public function test_fake_gps_during_session_revokes_token_and_flags_tracking(): void
    {
        $pers = $this->user('personel');
        $token = $pers->createToken('hp')->plainTextToken;

        $point = ['latitude' => -7.25, 'longitude' => 112.75, 'recorded_at' => now()->toIso8601String()];
        $this->withToken($token)->postJson('/api/mobile/location', ['points' => [$point]])->assertOk();

        $this->withToken($token)->postJson('/api/mobile/location', ['points' => [$point + ['is_mock' => true]]])
            ->assertStatus(403)->assertJsonPath('code', 'mock_location');

        // Token dicabut: permintaan berikutnya ditolak.
        $this->assertSame(0, $pers->tokens()->count());

        // Pantau Personel menandai lokasi palsu.
        $row = collect(PersonnelTracking::snapshot($this->user('admin')))->firstWhere('user_id', $pers->id);
        $this->assertTrue($row['mock_detected']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOKASI_PALSU', 'target_personnel' => $pers->name]);
    }

    public function test_tracking_page_lists_personel_who_never_sent_location(): void
    {
        $admin = $this->user('admin');
        $baru = $this->user('personel', ['name' => 'Personel Baru']);
        $this->user('personel', ['name' => 'Nonaktif', 'is_active' => false]);

        $this->actingAs($admin)->get(route('tracking.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('personnel', fn ($rows) => ($r = collect($rows)->firstWhere('user_id', $baru->id))
                    && $r['has_location'] === false && $r['latitude'] === null
                    && collect($rows)->pluck('name')->doesntContain('Nonaktif')));

        // API Android tetap hanya mengirim personel yang punya koordinat (kompatibel dengan APK lama).
        $this->assertNull(collect(PersonnelTracking::snapshot($admin))->firstWhere('user_id', $baru->id));
    }
}
