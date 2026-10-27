<?php

namespace Tests\Feature;

use App\Models\SpJaga;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengujian keamanan & kontrak API mobile.
 *
 * Catatan: test ini sengaja membangun skema minimal yang dibutuhkan secara
 * eksplisit agar kontrak API teruji terpisah dari riwayat migrasi.
 */
class MobileApiSecurityTest extends TestCase
{
    /** PNG 1x1 valid sebagai pengganti foto senyum dari kamera. */
    private const SMILE_PHOTO = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('nrp')->nullable();
            $t->string('pangkat')->nullable();
            $t->string('korps')->nullable();
            $t->string('jabatan')->nullable();
            $t->string('phone')->nullable();
            $t->string('avatar')->nullable();
            $t->string('role')->default('personel');
            $t->string('password');
            $t->boolean('is_active')->default(true);
            $t->boolean('can_access_mitra')->default(false);
            $t->boolean('can_access_technical_cash')->default(false);
            $t->boolean('can_manage_koperasi')->default(false);
            $t->boolean('can_access_ibu_beti')->default(false);
            $t->string('activation_token')->nullable();
            $t->boolean('must_change_password')->default(false);
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('attendances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id');
            $t->date('attendance_date');
            $t->string('time_in')->nullable();
            $t->string('status')->default('hadir');
            $t->string('ip_address')->nullable();
            $t->string('device')->nullable();
            $t->string('user_agent')->nullable();
            $t->double('latitude')->nullable();
            $t->double('longitude')->nullable();
            $t->string('location_name')->nullable();
            $t->text('notes')->nullable();
            $t->string('photo')->nullable();
            $t->unsignedTinyInteger('smile_score')->nullable();
            $t->timestamps();
        });
        Schema::create('personnel_locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique();
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->float('accuracy')->nullable();
            $t->float('speed')->nullable();
            $t->float('heading')->nullable();
            $t->unsignedTinyInteger('battery')->nullable();
            $t->boolean('is_charging')->nullable();
            $t->string('provider')->nullable();
            $t->string('device')->nullable();
            $t->timestamp('recorded_at');
            $t->timestamps();
        });
        Schema::create('personnel_location_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id');
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->float('accuracy')->nullable();
            $t->float('speed')->nullable();
            $t->unsignedTinyInteger('battery')->nullable();
            $t->timestamp('recorded_at');
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('app_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable();
            $t->string('role')->nullable();
            $t->string('title');
            $t->text('message');
            $t->string('type')->default('info');
            $t->string('link')->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestamps();
        });
        Schema::create('sp_jagas', function (Blueprint $t) {
            $t->id();
            $t->string('nomor_sprin')->nullable();
            $t->string('nomor_urut')->nullable();
            $t->integer('bulan');
            $t->integer('tahun');
            $t->string('bulan_romawi')->nullable();
            $t->date('tmt_mulai')->nullable();
            $t->date('tmt_selesai')->nullable();
            $t->date('tanggal_surat')->nullable();
            $t->foreignId('perwira_tertua_user_id')->nullable();
            $t->string('perwira_tertua_nama')->nullable();
            $t->string('perwira_tertua_pangkat_nrp')->nullable();
            $t->string('perwira_tertua_jabatan')->nullable();
            $t->integer('total_personel_count')->default(0);
            $t->string('total_personel_terbilang')->nullable();
            $t->string('ttd_type')->default('tte');
            $t->string('status')->default('draft');
            $t->string('signed_file_path')->nullable();
            $t->string('verification_code')->nullable();
            $t->foreignId('penandatangan_user_id')->nullable();
            $t->string('penandatangan_nama')->nullable();
            $t->string('penandatangan_pangkat_nrp')->nullable();
            $t->string('penandatangan_jabatan')->nullable();
            $t->foreignId('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('created_by');
            $t->timestamps();
        });
        Schema::create('sp_jaga_perwiras', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sp_jaga_id');
            $t->foreignId('user_id')->nullable();
            $t->integer('no_urut')->default(1);
            $t->string('nama');
            $t->string('pangkat_korps')->nullable();
            $t->string('nrp')->nullable();
            foreach (range(1, 7) as $i) {
                $t->string("tgl_{$i}")->nullable();
            }
            $t->json('tgl_list')->nullable();
            $t->timestamps();
        });
        Schema::create('sp_jaga_anggotas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sp_jaga_id');
            $t->integer('divisi_no')->default(1);
            $t->string('tanggal_list_text')->nullable();
            $t->json('tanggal_array')->nullable();
            $t->json('anggota_items')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Di produksi setiap request adalah proses baru; di test, guard auth tetap
     * hidup antar-request. Reset agar perilaku identik dengan produksi.
     */
    public function json($method, $uri, array $data = [], array $headers = [], $options = 0)
    {
        $this->app['auth']->forgetGuards();
        return parent::json($method, $uri, $data, $headers, $options);
    }

    private function makeUser(array $attrs = []): User
    {
        static $n = 0;
        $n++;
        return User::forceCreate(array_merge([
            'name' => "Personel {$n}",
            'email' => "p{$n}@example.test",
            'nrp' => "NRP{$n}",
            'role' => 'personel',
            'password' => Hash::make('rahasia123'),
            'is_active' => true,
        ], $attrs));
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_data_endpoints_reject_requests_without_token(): void
    {
        foreach (['/api/mobile/mitra', '/api/mobile/users', '/api/mobile/me', '/api/mobile/attendances', '/api/mobile/duty-roster'] as $uri) {
            $this->getJson($uri)->assertStatus(401)->assertJson(['code' => 'unauthenticated']);
        }
        $this->postJson('/api/mobile/users', ['name' => 'x', 'role' => 'admin'])->assertStatus(401);
        $this->deleteJson('/api/mobile/users/1')->assertStatus(401);
    }

    public function test_forged_identity_headers_are_ignored(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $this->withHeaders(['X-User-Id' => (string) $admin->id])
            ->getJson('/api/mobile/users')
            ->assertStatus(401);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withToken('1|bukan-token-asli')->getJson('/api/mobile/me')->assertStatus(401);
    }

    public function test_login_returns_permissions_and_working_token(): void
    {
        $this->makeUser(['nrp' => '12345', 'role' => 'personel']);

        $res = $this->postJson('/api/mobile/login', ['username' => '12345', 'password' => 'rahasia123'])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $perms = $res->json('user.permissions');
        $this->assertContains('duty_roster.view', $perms);
        $this->assertNotContains('mitra.view', $perms);
        $this->assertNotContains('users.manage', $perms);

        $this->withToken($res->json('token'))->getJson('/api/mobile/me')
            ->assertOk()->assertJsonPath('user.nrp', '12345');
    }

    public function test_login_error_does_not_reveal_whether_user_exists(): void
    {
        $this->makeUser(['nrp' => '777']);
        $a = $this->postJson('/api/mobile/login', ['username' => '777', 'password' => 'salah'])->assertStatus(401)->json('message');
        $b = $this->postJson('/api/mobile/login', ['username' => 'tidakada', 'password' => 'salah'])->assertStatus(401)->json('message');
        $this->assertSame($a, $b);
    }

    public function test_inactive_user_cannot_login_or_use_token(): void
    {
        $u = $this->makeUser(['nrp' => '888']);
        $token = $this->tokenFor($u);
        $u->forceFill(['is_active' => false])->save();

        $this->withToken($token)->getJson('/api/mobile/me')->assertStatus(403);
        $this->postJson('/api/mobile/login', ['username' => '888', 'password' => 'rahasia123'])->assertStatus(403);
    }

    public function test_permissions_are_enforced_by_backend(): void
    {
        $personel = $this->makeUser(['role' => 'personel']);
        $token = $this->tokenFor($personel);

        $this->withToken($token)->getJson('/api/mobile/audit-logs')
            ->assertStatus(403)->assertJsonPath('required_permission', 'audit_logs.view');
        $this->withToken($token)->getJson('/api/mobile/users')->assertStatus(403);
        $this->withToken($token)->postJson('/api/mobile/users', [
            'name' => 'Penyusup', 'email' => 'x@x.test', 'nrp' => '1', 'role' => 'admin', 'password' => 'rahasia123',
        ])->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'x@x.test']);
    }

    public function test_removed_cash_module_endpoints_are_gone(): void
    {
        // Modul Buku Kas & Kas Unit Teknis dihapus (commit 7e849d0); endpoint tidak boleh tersisa.
        $admin = $this->makeUser(['role' => 'admin']);
        $token = $this->tokenFor($admin);

        foreach (['/api/mobile/cash', '/api/mobile/technical-cash', '/api/mobile/pdf/cash'] as $uri) {
            $this->withToken($token)->getJson($uri)->assertNotFound();
        }

        $perms = $this->withToken($token)->getJson('/api/mobile/me')->json('user.permissions');
        $this->assertNotContains('cash.view', $perms);
        $this->assertNotContains('technical_cash.view', $perms);
    }

    public function test_permission_change_by_admin_applies_on_next_request(): void
    {
        $u = $this->makeUser(['role' => 'personel']);
        $token = $this->tokenFor($u);
        $this->withToken($token)->getJson('/api/mobile/me')->assertOk();
        $this->assertNotContains('mitra.view', $this->withToken($token)->getJson('/api/mobile/me')->json('user.permissions'));

        $u->forceFill(['can_access_mitra' => true])->save();
        $this->assertContains('mitra.view', $this->withToken($token)->getJson('/api/mobile/me')->json('user.permissions'));
    }

    public function test_anggota_sintel_gets_restricted_menu(): void
    {
        $sintel = $this->makeUser(['role' => 'anggotasintel']);
        $perms = $this->withToken($this->tokenFor($sintel))->getJson('/api/mobile/me')->json('user.permissions');
        $this->assertContains('duty_roster.view', $perms);
        $this->assertContains('attendance.create', $perms);
        $this->assertNotContains('letter_logs.view', $perms);
        $this->assertNotContains('skhpp.view', $perms);
    }

    public function test_attendance_is_recorded_for_token_owner_only(): void
    {
        Storage::fake('public');
        $victim = $this->makeUser();
        $actor = $this->makeUser();

        $this->withToken($this->tokenFor($actor))
            ->withHeaders(['X-User-Id' => (string) $victim->id])
            ->postJson('/api/mobile/attendances', [
                'status' => 'hadir', 'user_id' => $victim->id,
                'latitude' => -7.2575, 'longitude' => 112.7521,
                'photo' => self::SMILE_PHOTO, 'smile_score' => 87,
            ])
            ->assertOk()
            ->assertJsonPath('attendance.smile_score', 87);

        $this->assertSame(1, DB::table('attendances')->where('user_id', $actor->id)->count());
        $this->assertSame(0, DB::table('attendances')->where('user_id', $victim->id)->count());

        // Tanggal presensi harus dikirim sebagai Y-m-d lokal (bukan ISO UTC yang bergeser sehari).
        $this->withToken($this->tokenFor($actor))->getJson('/api/mobile/attendances')
            ->assertOk()
            ->assertJsonPath('today_attendance.attendance_date', now()->toDateString());

        // Titik presensi ikut memperbarui posisi personel di peta pemantauan.
        $this->assertSame(1, DB::table('personnel_locations')->where('user_id', $actor->id)->count());
    }

    public function test_attendance_requires_gps_and_smile_photo(): void
    {
        Storage::fake('public');
        $token = $this->tokenFor($this->makeUser());

        $this->withToken($token)->postJson('/api/mobile/attendances', ['status' => 'hadir', 'photo' => self::SMILE_PHOTO])
            ->assertStatus(422)->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->withToken($token)->postJson('/api/mobile/attendances', ['status' => 'hadir', 'latitude' => -7.25, 'longitude' => 112.75])
            ->assertStatus(422)->assertJsonValidationErrors(['photo']);
        $this->withToken($token)->postJson('/api/mobile/attendances', [
            'status' => 'hadir', 'latitude' => -7.25, 'longitude' => 112.75, 'photo' => 'data:image/jpeg;base64,bukan-gambar',
        ])->assertStatus(422)->assertJsonValidationErrors(['photo']);

        $this->assertSame(0, DB::table('attendances')->count());
    }

    public function test_location_report_and_tracking_visibility_by_role(): void
    {
        $personel = $this->makeUser(['name' => 'Budi', 'role' => 'personel']);
        $this->withToken($this->tokenFor($personel))->postJson('/api/mobile/location', [
            'device_model' => 'Samsung A54',
            'points' => [
                ['latitude' => -7.20, 'longitude' => 112.70, 'battery' => 80, 'recorded_at' => now()->subMinutes(2)->toIso8601String()],
                ['latitude' => -7.21, 'longitude' => 112.71, 'battery' => 79, 'accuracy' => 8, 'recorded_at' => now()->subMinute()->toIso8601String()],
            ],
        ])->assertOk()->assertJsonPath('accepted', 2);

        // Titik lama yang datang belakangan tidak boleh menimpa posisi terakhir.
        $this->withToken($this->tokenFor($personel))->postJson('/api/mobile/location', [
            'latitude' => -7.99, 'longitude' => 112.99, 'recorded_at' => now()->subHour()->toIso8601String(),
        ])->assertOk();

        $this->assertSame(3, DB::table('personnel_location_histories')->where('user_id', $personel->id)->count());

        // Personel biasa tidak boleh melihat posisi orang lain.
        $this->withToken($this->tokenFor($personel))->getJson('/api/mobile/tracking')
            ->assertStatus(403)->assertJsonPath('required_permission', 'tracking.view');
        $this->assertNotContains('tracking.view', $this->withToken($this->tokenFor($personel))->getJson('/api/mobile/me')->json('user.permissions'));

        foreach (['admin', 'super_admin', 'komandan'] as $role) {
            $viewer = $this->makeUser(['role' => $role]);
            $res = $this->withToken($this->tokenFor($viewer))->getJson('/api/mobile/tracking')->assertOk();
            $budi = collect($res->json('personnel'))->firstWhere('user_id', $personel->id);
            $this->assertEqualsWithDelta(-7.21, $budi['latitude'], 0.00001, "role {$role}");
            $this->assertSame(79, $budi['battery']);
            $this->assertSame('Samsung A54', $budi['device']);
            $this->assertTrue($budi['is_online']);

            $trail = $this->withToken($this->tokenFor($viewer))->getJson("/api/mobile/tracking/{$personel->id}/history?hours=24")
                ->assertOk()->json('points');
            $this->assertCount(3, $trail);
        }
    }

    public function test_location_report_rejects_invalid_coordinates_and_requires_token(): void
    {
        $this->postJson('/api/mobile/location', ['latitude' => -7.2, 'longitude' => 112.7])->assertStatus(401);

        $token = $this->tokenFor($this->makeUser());
        $this->withToken($token)->postJson('/api/mobile/location', ['latitude' => 120, 'longitude' => 112.7])->assertStatus(422);
        $this->withToken($token)->postJson('/api/mobile/location', ['points' => []])->assertStatus(422);
    }

    public function test_activation_requires_valid_token(): void
    {
        $u = $this->makeUser(['nrp' => '999', 'is_active' => false, 'activation_token' => 'TOKEN-ABC']);
        $oldHash = $u->password;

        $this->postJson('/api/mobile/activate', ['nrp' => '999', 'password' => 'barubaru123'])->assertStatus(422);
        $this->postJson('/api/mobile/activate', ['nrp' => '999', 'token' => 'SALAH', 'password' => 'barubaru123'])->assertStatus(422);
        $this->assertSame($oldHash, $u->fresh()->password);

        $this->postJson('/api/mobile/activate', ['nrp' => '999', 'token' => 'TOKEN-ABC', 'password' => 'barubaru123'])->assertOk();
        $this->assertTrue($u->fresh()->is_active);
        $this->assertNull($u->fresh()->activation_token);
    }

    public function test_logout_revokes_token(): void
    {
        $u = $this->makeUser();
        $token = $this->tokenFor($u);
        $this->withToken($token)->postJson('/api/mobile/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/mobile/me')->assertStatus(401);
    }

    public function test_notifications_are_scoped_to_user(): void
    {
        $me = $this->makeUser(['role' => 'personel']);
        $other = $this->makeUser(['role' => 'komandan']);
        DB::table('app_notifications')->insert([
            ['user_id' => $me->id, 'role' => null, 'title' => 'Untuk saya', 'message' => 'a', 'type' => 'info', 'is_read' => false, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $other->id, 'role' => null, 'title' => 'Rahasia komandan', 'message' => 'b', 'type' => 'info', 'is_read' => false, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => null, 'role' => 'komandan', 'title' => 'Role komandan', 'message' => 'c', 'type' => 'info', 'is_read' => false, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => null, 'role' => null, 'title' => 'Broadcast', 'message' => 'd', 'type' => 'info', 'is_read' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $titles = collect($this->withToken($this->tokenFor($me))->getJson('/api/mobile/notifications')->json('notifications'))->pluck('title')->all();
        sort($titles);
        $this->assertSame(['Broadcast', 'Untuk saya'], $titles);
    }

    public function test_duty_roster_is_built_from_sp_jaga_and_marks_my_schedule(): void
    {
        $me = $this->makeUser(['name' => 'Bambang', 'nrp' => '82068']);
        $creator = $this->makeUser(['role' => 'admin']);
        $sp = SpJaga::forceCreate([
            'nomor_sprin' => 'Sprin/ 29 /X/2026', 'bulan' => 10, 'tahun' => 2026,
            'tmt_mulai' => '2026-10-01', 'tmt_selesai' => '2026-10-31',
            'status' => 'published', 'ttd_type' => 'tte', 'created_by' => $creator->id,
        ]);
        DB::table('sp_jaga_perwiras')->insert([
            'sp_jaga_id' => $sp->id, 'nama' => 'Bambang', 'pangkat_korps' => 'Peltu Saa', 'nrp' => '82068',
            'tgl_1' => '04', 'tgl_2' => '11', 'tgl_3' => '-', 'tgl_4' => '-', 'tgl_5' => '-', 'tgl_6' => '-', 'tgl_7' => '-',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sp_jaga_anggotas')->insert([
            'sp_jaga_id' => $sp->id, 'divisi_no' => 2, 'tanggal_list_text' => '05, 11 OKTOBER 2026',
            'anggota_items' => json_encode([['nama' => 'Erwan Junaidi', 'pangkat_korps' => 'Peltu Ttg', 'nrp_nip' => '84025', 'role_jaga' => 'BAGA']]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // SP draft tidak boleh terlihat oleh personel biasa
        SpJaga::forceCreate(['bulan' => 11, 'tahun' => 2026, 'status' => 'draft', 'created_by' => $creator->id]);

        $res = $this->withToken($this->tokenFor($me))
            ->getJson('/api/mobile/duty-roster?bulan=10&tahun=2026')
            ->assertOk()
            ->assertJsonPath('sp.id', $sp->id)
            ->assertJsonPath('my_dates', ['2026-10-04', '2026-10-11']);

        $days = collect($res->json('days'))->keyBy('date');
        $this->assertSame(['2026-10-04', '2026-10-05', '2026-10-11'], $days->keys()->all());
        $this->assertTrue($days['2026-10-04']['has_me']);
        $this->assertFalse($days['2026-10-05']['has_me']);
        $this->assertSame('Bintara Jaga (BAGA)', $days['2026-10-05']['entries'][0]['role']);
        $this->assertCount(2, $days['2026-10-11']['entries']);
        $this->assertCount(1, $res->json('available'), 'SP draft tidak boleh tampil untuk personel');

        $this->withToken($this->tokenFor($me))
            ->getJson('/api/mobile/duty-roster?bulan=11&tahun=2026')
            ->assertOk()->assertJsonPath('sp', null);
    }

    public function test_dashboard_only_contains_permitted_modules(): void
    {
        Schema::create('letter_logs', function (Blueprint $t) {
            $t->id(); $t->foreignId('category_id')->nullable(); $t->string('sequence')->nullable(); $t->string('full_number')->nullable();
            $t->string('subject')->nullable(); $t->string('recipient')->nullable(); $t->date('date')->nullable(); $t->timestamps();
        });
        Schema::create('skhpps', function (Blueprint $t) {
            $t->id(); $t->string('status')->default('pending'); $t->foreignId('submitted_by')->nullable(); $t->timestamps();
        });
        Schema::create('signature_requests', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable(); $t->string('status')->default('pending'); $t->timestamps();
        });
        $personel = $this->makeUser(['role' => 'personel']);
        $summary = $this->withToken($this->tokenFor($personel))->getJson('/api/mobile/dashboard')
            ->assertOk()->json('summary');
        $this->assertArrayNotHasKey('signature_pending', $summary);
        $this->assertArrayNotHasKey('cash_balance', $summary);
        $this->assertArrayHasKey('attendance', $summary);
        $this->assertArrayHasKey('letter_logs_total', $summary);

        $admin = $this->makeUser(['role' => 'admin']);
        DB::table('signature_requests')->insert(['status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $adminSummary = $this->withToken($this->tokenFor($admin))->getJson('/api/mobile/dashboard')
            ->assertOk()->json('summary');
        $this->assertEquals(1, $adminSummary['signature_pending']);
    }

    public function test_unpublished_sp_jaga_pdf_is_forbidden_for_regular_personnel(): void
    {
        $me = $this->makeUser();
        $creator = $this->makeUser(['role' => 'admin']);
        $draft = SpJaga::forceCreate(['bulan' => 11, 'tahun' => 2026, 'status' => 'draft', 'created_by' => $creator->id]);
        $this->withToken($this->tokenFor($me))->getJson("/api/mobile/pdf/sp-jaga/{$draft->id}")->assertStatus(403);
    }

    public function test_laravel_log_viewer_is_no_longer_public(): void
    {
        $this->get('/admin/laravel-logs')->assertRedirect();
    }
}
