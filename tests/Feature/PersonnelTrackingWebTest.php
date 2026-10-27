<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PersonnelTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonnelTrackingWebTest extends TestCase
{
    use RefreshDatabase;

    private const SMILE_PHOTO = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
            'mfa_enabled' => false,
        ]);
    }

    public function test_only_admin_super_admin_and_komandan_can_open_tracking_page(): void
    {
        $this->actingAs($this->user('personel'))->get('/pantau-personel')->assertForbidden();
        $this->actingAs($this->user('personel'))->getJson('/pantau-personel/data')->assertForbidden();

        $budi = $this->user('personel');
        PersonnelTracking::record($budi, [['latitude' => -7.25, 'longitude' => 112.75, 'battery' => 64]]);

        foreach (['admin', 'super_admin', 'komandan'] as $role) {
            $viewer = $this->user($role);
            $this->actingAs($viewer)->get('/pantau-personel')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Tracking/Index')
                    ->where('personnel.0.user_id', $budi->id)
                    ->where('personnel.0.is_online', true));

            $this->actingAs($viewer)->getJson('/pantau-personel/data')
                ->assertOk()->assertJsonPath('personnel.0.battery', 64);
            $this->actingAs($viewer)->getJson("/pantau-personel/{$budi->id}/jejak?hours=6")
                ->assertOk()->assertJsonCount(1, 'points');
        }
    }

    public function test_web_attendance_requires_gps_and_photo_and_updates_tracking(): void
    {
        Storage::fake('public');
        $user = $this->user('personel');

        $this->actingAs($user)->post('/attendances', ['status' => 'hadir', 'photo' => self::SMILE_PHOTO])
            ->assertSessionHasErrors(['latitude', 'longitude']);
        $this->actingAs($user)->post('/attendances', ['status' => 'hadir', 'latitude' => -7.25, 'longitude' => 112.75])
            ->assertSessionHasErrors(['photo']);

        $this->actingAs($user)->post('/attendances', [
            'status' => 'hadir', 'latitude' => -7.25, 'longitude' => 112.75,
            'location_name' => 'Ujung, Surabaya', 'photo' => self::SMILE_PHOTO, 'smile_score' => 72,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendances', ['user_id' => $user->id, 'smile_score' => 72]);
        $this->assertDatabaseHas('personnel_locations', ['user_id' => $user->id, 'provider' => 'attendance']);
    }

    public function test_history_older_than_retention_is_pruned(): void
    {
        $user = $this->user('personel');
        PersonnelTracking::record($user, [
            ['latitude' => -7.1, 'longitude' => 112.1, 'recorded_at' => now()->subDays(40)->toIso8601String()],
            ['latitude' => -7.2, 'longitude' => 112.2],
        ]);

        $this->assertSame(1, PersonnelTracking::pruneHistory());
        $this->assertDatabaseCount('personnel_location_histories', 1);
    }
}
