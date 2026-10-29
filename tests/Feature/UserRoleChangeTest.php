<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Ubah peran personel dari Kelola Pengguna: siapa boleh mengubah apa. */
class UserRoleChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::fake(); // pesan WhatsApp aktivasi
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'is_active' => true, 'must_change_password' => false, 'mfa_enabled' => false], $attrs));
    }

    private function payload(User $u, array $over = []): array
    {
        return array_merge(['name' => $u->name, 'pangkat' => $u->pangkat ?: 'Serka', 'nrp' => $u->nrp ?: 'N' . $u->id, 'email' => $u->email, 'phone' => $u->phone], $over);
    }

    public function test_admin_changes_role_and_jabatan_but_cannot_grant_super_admin(): void
    {
        $admin = $this->user('admin');
        $pers = $this->user('personel');

        $this->actingAs($admin)->put(route('users.update', $pers), $this->payload($pers, ['role' => 'komandan', 'jabatan' => 'Komandan Detasemen']))
            ->assertSessionHasNoErrors();
        $this->assertSame('komandan', $pers->fresh()->role);
        $this->assertSame('Komandan Detasemen', $pers->fresh()->jabatan);

        $this->actingAs($admin)->put(route('users.update', $pers), $this->payload($pers->fresh(), ['role' => 'super_admin']))
            ->assertSessionHasErrors('role');
        $this->assertSame('komandan', $pers->fresh()->role, 'Admin tidak boleh memberi peran Super Admin');

        // Admin juga tidak boleh menurunkan Super Admin.
        $super = $this->user('super_admin');
        $this->actingAs($admin)->put(route('users.update', $super), $this->payload($super, ['role' => 'personel']))
            ->assertForbidden();
        $this->assertSame('super_admin', $super->fresh()->role);
    }

    public function test_non_super_admin_cannot_see_or_manage_super_admin_accounts(): void
    {
        $admin = $this->user('admin');
        $super = $this->user('super_admin', ['name' => 'Akun Super Rahasia']);
        $pers = $this->user('personel');

        $this->actingAs($admin)->get(route('users.index'))
            ->assertInertia(fn ($page) => $page
                ->where('allUsers', fn ($users) => collect($users)->pluck('id')->doesntContain($super->id) && collect($users)->pluck('id')->contains($pers->id)));

        $this->actingAs($admin)->put(route('users.update', $super), $this->payload($super, ['name' => 'Diubah']))->assertForbidden();
        $this->actingAs($admin)->patch(route('users.toggle', $super), ['password' => 'password'])->assertForbidden();
        $this->actingAs($admin)->post(route('users.generate-token', $super))->assertForbidden();
        $this->actingAs($admin)->post(route('users.change-password', $super), ['password' => 'baru1234'])->assertForbidden();
        $this->actingAs($admin)->post(route('users.toggle-mfa', $super))->assertForbidden();
        $this->actingAs($admin)->post(route('users.toggle-koperasi-access', $super))->assertForbidden();
        $this->actingAs($admin)->post(route('users.toggle-mitra-access', $super->id))->assertForbidden();
        $this->actingAs($admin)->delete(route('users.destroy', $super))->assertForbidden();

        $fresh = $super->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('Akun Super Rahasia', $fresh->name);
        $this->assertTrue($fresh->is_active);

        // Super Admin tetap bisa melihat & mengelola sesama Super Admin.
        $other = $this->user('super_admin');
        $this->actingAs($other)->get(route('users.index'))
            ->assertInertia(fn ($page) => $page
                ->where('allUsers', fn ($users) => collect($users)->pluck('id')->contains($super->id)));
        $this->actingAs($other)->put(route('users.update', $super), $this->payload($super, ['name' => 'Diubah Super']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Diubah Super', $super->fresh()->name);
    }

    public function test_mobile_api_hides_and_protects_super_admin_accounts(): void
    {
        $admin = $this->user('admin');
        $super = $this->user('super_admin');
        $token = $admin->createToken('test')->plainTextToken;

        $ids = collect($this->withToken($token)->getJson('/api/mobile/users')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($super->id));

        $this->withToken($token)->putJson("/api/mobile/users/{$super->id}", ['is_active' => false])->assertForbidden();
        $this->withToken($token)->deleteJson("/api/mobile/users/{$super->id}")->assertForbidden();
        $this->withToken($token)->postJson('/api/mobile/users', ['name' => 'X', 'email' => 'x@x.test', 'nrp' => 'X1', 'role' => 'super_admin', 'password' => 'rahasia123'])
            ->assertForbidden();

        $this->assertTrue($super->fresh()->is_active);
        $this->assertNull(User::where('nrp', 'X1')->first());
    }

    public function test_super_admin_can_grant_super_admin_but_not_change_own_role(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $this->actingAs($super)->put(route('users.update', $admin), $this->payload($admin, ['role' => 'super_admin']))
            ->assertSessionHasNoErrors();
        $this->assertSame('super_admin', $admin->fresh()->role);

        $this->actingAs($super)->put(route('users.update', $super), $this->payload($super, ['role' => 'personel']))
            ->assertSessionHasErrors('role');
        $this->assertSame('super_admin', $super->fresh()->role);
    }

    public function test_creating_super_admin_requires_super_admin_and_roles_are_whitelisted(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('users.store'), ['name' => 'Baru', 'pangkat' => 'Serka', 'nrp' => '777', 'phone' => '0811', 'email' => 'baru@x.test', 'role' => 'super_admin'])
            ->assertSessionHasErrors('role');
        $this->assertNull(User::where('nrp', '777')->first());

        $this->actingAs($admin)->post(route('users.store-bulk'), ['users' => [['name' => 'Massal', 'pangkat' => 'Serka', 'nrp' => '888', 'role' => 'super_admin']]])
            ->assertSessionHasErrors('users');
        $this->actingAs($admin)->post(route('users.store-bulk'), ['users' => [['name' => 'Aneh', 'pangkat' => 'Serka', 'nrp' => '889', 'role' => 'raja']]])
            ->assertSessionHasErrors('users.0.role');
        $this->assertSame(0, User::whereIn('nrp', ['888', '889'])->count());
    }
}
