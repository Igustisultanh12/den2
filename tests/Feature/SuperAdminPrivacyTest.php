<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\VisitorLog;
use App\Support\PersonnelTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Lokasi & log Super Admin hanya terlihat oleh sesama Super Admin. */
class SuperAdminPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::fake();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true, 'must_change_password' => false, 'mfa_enabled' => false]);
    }

    private function point(User $u): void
    {
        PersonnelTracking::record($u, [['latitude' => -7.2, 'longitude' => 112.7, 'recorded_at' => now()->toIso8601String()]]);
    }

    public function test_super_admin_location_is_hidden_from_lower_roles(): void
    {
        $admin = $this->user('admin');
        $komandan = $this->user('komandan');
        $super = $this->user('super_admin');
        $pers = $this->user('personel');
        $this->point($super);
        $this->point($pers);

        foreach ([$admin, $komandan] as $viewer) {
            $ids = collect($this->actingAs($viewer)->getJson(route('tracking.data'))->assertOk()->json('personnel'))->pluck('user_id');
            $this->assertFalse($ids->contains($super->id));
            $this->assertTrue($ids->contains($pers->id));
            $this->actingAs($viewer)->getJson(route('tracking.history', $super->id))->assertForbidden();

            $token = $viewer->createToken('t')->plainTextToken;
            $ids = collect($this->withToken($token)->getJson('/api/mobile/tracking')->assertOk()->json('personnel'))->pluck('user_id');
            $this->assertFalse($ids->contains($super->id));
            $this->withToken($token)->getJson("/api/mobile/tracking/{$super->id}/history")->assertForbidden();
        }

        $other = $this->user('super_admin');
        $ids = collect($this->actingAs($other)->getJson(route('tracking.data'))->assertOk()->json('personnel'))->pluck('user_id');
        $this->assertTrue($ids->contains($super->id));
        $this->actingAs($other)->getJson(route('tracking.history', $super->id))->assertOk();
    }

    public function test_super_admin_logs_are_hidden_from_lower_roles(): void
    {
        $admin = $this->user('admin');
        $super = $this->user('super_admin');

        $superVisit = VisitorLog::create(['user_id' => $super->id, 'user_name' => $super->name, 'ip_address' => '10.0.0.1']);
        $guestVisit = VisitorLog::create(['user_id' => null, 'user_name' => 'Tamu', 'ip_address' => '10.0.0.2']);
        $superAudit = AuditLog::create(['user_id' => $super->id, 'admin_name' => $super->name, 'action' => 'RAHASIA', 'target_personnel' => 'x', 'description' => 'aksi super', 'ip_address' => '10.0.0.1']);
        $adminAudit = AuditLog::create(['user_id' => $admin->id, 'admin_name' => $admin->name, 'action' => 'BIASA', 'target_personnel' => 'x', 'description' => 'aksi admin', 'ip_address' => '10.0.0.3']);

        $this->actingAs($admin)->get(route('visitor-logs.index'))
            ->assertInertia(fn ($p) => $p->where('logs.data', fn ($rows) => collect($rows)->pluck('id')->doesntContain($superVisit->id) && collect($rows)->pluck('id')->contains($guestVisit->id)));
        $this->actingAs($admin)->get(route('audit-logs.index'))
            ->assertInertia(fn ($p) => $p->where('logs.data', fn ($rows) => collect($rows)->pluck('id')->doesntContain($superAudit->id) && collect($rows)->pluck('id')->contains($adminAudit->id)));

        $token = $admin->createToken('t')->plainTextToken;
        $res = $this->withToken($token)->getJson('/api/mobile/audit-logs')->assertOk();
        $this->assertFalse(collect($res->json('visitor_logs'))->pluck('id')->contains($superVisit->id));
        $this->assertFalse(collect($res->json('audit_logs'))->pluck('id')->contains($superAudit->id));

        // Admin tidak dapat menghapus log Super Admin, termasuk lewat "hapus semua".
        $this->actingAs($admin)->delete(route('visitor-logs.destroy', $superVisit->id));
        $this->actingAs($admin)->post(route('visitor-logs.clear'));
        $this->assertNotNull($superVisit->fresh());
        $this->assertNull($guestVisit->fresh());

        // Super Admin melihat semuanya.
        $this->actingAs($super)->get(route('audit-logs.index'))
            ->assertInertia(fn ($p) => $p->where('logs.data', fn ($rows) => collect($rows)->pluck('id')->contains($superAudit->id)));
        $this->actingAs($super)->get(route('visitor-logs.index'))
            ->assertInertia(fn ($p) => $p->where('logs.data', fn ($rows) => collect($rows)->pluck('id')->contains($superVisit->id)));
    }
}
