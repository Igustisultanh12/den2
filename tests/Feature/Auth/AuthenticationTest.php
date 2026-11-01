<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        // Akun baru default nonaktif & wajib ganti password; uji jalur login akun aktif biasa.
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'mfa_enabled' => false,
        ]);

        // Personel wajib mengirim lokasi GPS saat login (App\Support\LocationGuard).
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'latitude' => -7.2575,
            'longitude' => 112.7521,
            'accuracy' => 15,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
