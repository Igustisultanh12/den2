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
