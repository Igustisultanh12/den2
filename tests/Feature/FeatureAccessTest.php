<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\FeatureAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Admin / Super Admin mengaktifkan & menonaktifkan fitur menu per personel. */
class FeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::fake();
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'is_active' => true, 'must_change_password' => false, 'mfa_enabled' => false], $attrs));
    }

    public function test_default_features_follow_role(): void
    {
        // Role Personel: bawaannya hanya Chat, SP Jaga & Backup.
        $pers = $this->user('personel');
        $this->assertNull($pers->feature_access);
        $this->assertSame(['chat', 'sp_jaga', 'backup'], FeatureAccess::enabledFor($pers));
        $this->assertFalse($pers->hasFeature('skhpp'));
        $this->assertFalse($pers->hasFeature('letter_logs'));
        $this->assertFalse($pers->hasFeature('tracking'));
        // Akses Mitra lama yang pernah diberikan Admin tetap berlaku.
        $this->assertSame(['chat', 'sp_jaga', 'mitra', 'backup'],FeatureAccess::enabledFor($this->user('personel', ['can_access_mitra' => true])));

        // Peran lain tetap memakai bawaan sebelumnya.
        $this->assertTrue($this->user('komandan')->hasFeature('tracking'));
        $this->assertTrue($this->user('komandan')->hasFeature('signature'));
        $this->assertSame(['sc_submissions', 'sp_jaga'], FeatureAccess::enabledFor($this->user('anggotasintel')));
        $this->assertSame(FeatureAccess::keys(), FeatureAccess::enabledFor($this->user('admin')));
    }

    public function test_admin_limits_personel_menu_and_routes(): void
    {
        $admin = $this->user('admin');
        $pers = $this->user('personel');

        // Bawaan personel: SKHPP tidak tampil sampai diberikan Admin.
        $this->actingAs($pers)->get(route('skhpp.index'))->assertForbidden();
        $this->actingAs($pers)->get(route('skhpp-archives.index'))->assertForbidden();
        $this->actingAs($pers)->get(route('sp-jaga.index'))->assertOk();

        $this->actingAs($admin)
            ->postJson(route('users.update-features', $pers), ['features' => ['skhpp_archive', 'backup', 'tidak_ada']])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->postJson(route('users.update-features', $pers), ['features' => ['skhpp_archive', 'sp_jaga']])
            ->assertOk()
            ->assertJsonPath('features', ['skhpp_archive', 'sp_jaga'])
            ->assertJsonPath('is_custom', true);

        $pers->refresh();
        $this->assertSame(['skhpp_archive', 'sp_jaga'], $pers->feature_access);

        // Fitur yang dicabut ditolak walau URL dibuka langsung.
        $this->actingAs($pers)->get(route('skhpp.index'))->assertForbidden();
        $this->actingAs($pers)->get(route('letter-logs.index'))->assertForbidden();
        $this->actingAs($pers)->get(route('chat.index'))->assertForbidden();

        // Menu (props Inertia) hanya berisi fitur yang diberikan.
        $this->actingAs($pers)->get(route('skhpp-archives.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SkhppArchive/Index')
                ->where('auth.user.features', ['skhpp_archive', 'sp_jaga']));

        // Tercatat di audit.
        $this->assertDatabaseHas('audit_logs', ['action' => 'UPDATE AKSES FITUR', 'target_personnel' => $pers->name]);

        // Kembalikan ke bawaan peran.
        $this->actingAs($admin)->postJson(route('users.update-features', $pers), ['reset' => true])
            ->assertOk()->assertJsonPath('is_custom', false);
        $this->assertNull($pers->fresh()->feature_access);
        $this->actingAs($pers->fresh())->get(route('skhpp-archives.index'))->assertForbidden();
        $this->actingAs($pers->fresh())->get(route('chat.index'))->assertOk();
    }

    public function test_admin_accounts_cannot_be_limited_and_personel_cannot_change_access(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');
        $pers = $this->user('personel');

        $this->actingAs($super)->post(route('users.update-features', $admin), ['features' => []])
            ->assertSessionHas('error');
        $this->assertNull($admin->fresh()->feature_access);
        $this->assertTrue($admin->fresh()->hasFeature('skhpp'));

        $this->actingAs($pers)->postJson(route('users.update-features', $this->user('personel')), ['features' => []])
            ->assertForbidden();
    }

    public function test_granting_backup_feature_opens_menu_for_personel(): void
    {
        $admin = $this->user('admin');
        $pers = $this->user('personel');

        $this->assertFalse($pers->hasFeature('skhpp'));
        $this->actingAs($admin)->postJson(route('users.update-features', $pers), ['features' => ['skhpp', 'backup']])->assertOk();

        $pers->refresh();
        $this->assertTrue($pers->hasFeature('skhpp'));
        $this->assertTrue($pers->hasFeature('backup'));
        $this->assertFalse($pers->hasFeature('chat'), 'Chat dicabut karena tidak dicentang');
        $this->actingAs($pers)->get(route('skhpp.index'))->assertOk();
        $this->assertTrue(\App\Support\MobileAccess::can($pers, 'skhpp.view'));
    }

    public function test_mitra_and_violations_pages_render_for_users_with_access(): void
    {
        // Regresi: MitraPaymentController@index memakai $canAccess yang sudah dihapus
        // sehingga /mitras selalu 500 "Undefined variable $canAccess".
        foreach (['admin', 'super_admin', 'komandan'] as $role) {
            $u = $this->user($role, ['can_access_mitra' => true]);
            $this->actingAs($u)->get(route('mitras.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('Mitra/Index')->where('canEdit', true));
            $this->actingAs($u)->get(route('soldier-violations.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('SoldierViolations/Index'));
        }

        $pers = $this->user('personel', ['feature_access' => ['mitra', 'violations']]);
        $this->actingAs($pers)->get(route('mitras.index'))->assertOk();
        $this->actingAs($pers)->get(route('soldier-violations.index'))->assertOk();
    }

    public function test_users_page_ships_feature_catalog_and_effective_features(): void
    {
        $admin = $this->user('admin');
        $pers = $this->user('personel', ['feature_access' => ['mitra']]);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('featureCatalog', count(FeatureAccess::FEATURES))
                ->where('allUsers', fn ($users) => collect($users)->firstWhere('id', $pers->id)['effective_features'] === ['mitra']));
    }
}
