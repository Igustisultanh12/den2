<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // Halaman depan membaca tabel settings, jadi skema migrasi harus tersedia.
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Halaman depan mengarahkan tamu ke login; halaman login harus tampil.
        $this->get('/')->assertRedirect(route('login', absolute: false));

        $response = $this->get('/login');

        $response->assertStatus(200);
    }
}
