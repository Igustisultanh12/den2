<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SafeImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regresi keamanan unggahan: polyglot "JPG berisi PHP", nama berkas dari klien,
 * path traversal, dan token penerima PESS.
 */
class UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1PX = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private function pngWithPhpPayload(): string
    {
        // Gambar valid + kode PHP ditempel di belakang marker akhir (polyglot klasik).
        return base64_decode(self::PNG_1PX) . '<?php echo "uji-sisinden"; ?>';
    }

    public function test_reencode_strips_payload_appended_to_image(): void
    {
        $clean = SafeImage::reencode($this->pngWithPhpPayload());

        $this->assertNotNull($clean);
        $this->assertStringStartsWith("\xFF\xD8\xFF", $clean, 'Hasil harus JPEG baru');
        $this->assertStringNotContainsString('<?php', $clean);
    }

    public function test_data_url_rejects_non_images_and_fake_types(): void
    {
        $this->assertNull(SafeImage::fromDataUrl('data:image/jpeg;base64,' . base64_encode('<?php echo 1; ?>')));
        $this->assertNull(SafeImage::fromDataUrl('data:text/html;base64,' . base64_encode('<script>alert(1)</script>')));
        $this->assertNull(SafeImage::fromDataUrl('attendances/foto_orang_lain.jpg'));
        $this->assertNull(SafeImage::fromDataUrl('https://evil.example/x.jpg'));
        $this->assertNotNull(SafeImage::fromDataUrl('data:image/png;base64,' . self::PNG_1PX));
    }

    /** Berkas unggahan uji dari file sementara biasa (tmpfile() terkunci di Windows). */
    private function upload(string $clientName, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return new UploadedFile($path, $clientName, null, null, true);
    }

    public function test_store_upload_uses_content_extension_not_client_name(): void
    {
        Storage::fake('public');

        // Konten PHP dengan nama .jpg: harus ditolak (isi terdeteksi sebagai php/teks).
        $php = $this->upload('foto.jpg', '<?php echo "uji-sisinden"; ?>');
        $this->assertNull(SafeImage::storeUpload($php, 'uji', ['pdf', 'jpg', 'png']));

        // Gambar asli bernama .php: disimpan sebagai .png hasil encode ulang, nama acak.
        $img = $this->upload('shell.php', $this->pngWithPhpPayload());
        $path = SafeImage::storeUpload($img, 'uji', ['pdf', 'jpg', 'png']);
        $this->assertNotNull($path);
        $this->assertStringEndsWith('.png', $path);
        $this->assertStringNotContainsString('.php', $path);
        $this->assertStringNotContainsString('<?php', Storage::disk('public')->get($path));

        // PDF asli tetap diterima.
        $pdf = $this->upload('surat.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $this->assertStringEndsWith('.pdf', (string) SafeImage::storeUpload($pdf, 'uji', ['pdf']));
    }

    public function test_pess_receiver_fails_closed_without_configured_token(): void
    {
        config(['services.pess.token' => null]);
        $this->postJson('/api/v1/pess/receive-submission', [], ['Authorization' => 'Bearer Malang101202...!!!???Benteng'])
            ->assertStatus(401);

        config(['services.pess.token' => 'token-rahasia-uji']);
        $this->postJson('/api/v1/pess/receive-submission', [], ['Authorization' => 'Bearer salah'])
            ->assertStatus(401);
    }

    public function test_violation_preview_blocks_path_traversal(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'must_change_password' => false, 'mfa_enabled' => false]);
        Storage::disk('public')->put('pelanggaran/lampiran/ok.pdf', '%PDF-1.4');

        $route = route('soldier-violations.preview-file', [], false);
        $this->actingAs($admin)->get($route . '?file_path=' . urlencode('../../../.env'))->assertNotFound();
        $this->actingAs($admin)->get($route . '?file_path=' . urlencode('pelanggaran/../../../composer.json'))->assertNotFound();

        Storage::disk('public')->delete('pelanggaran/lampiran/ok.pdf');
    }

    public function test_activation_and_custom_reset_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/aktivasi', ['nrp' => 'x', 'token' => 'SALAH' . $i, 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123']);
        }
        // Diblokir: kembali ke formulir dengan pesan jelas (bukan halaman 429 polos),
        // dan percobaan ke-6 tidak diproses sama sekali.
        $this->post('/aktivasi', ['nrp' => 'x', 'token' => 'SALAH', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertRedirect()
            ->assertSessionHasErrors('nrp');
        $this->assertStringContainsString('Terlalu banyak percobaan aktivasi', session('errors')->first('nrp'));

        // Lewat API (JSON) tetap berstatus 429.
        $this->postJson('/aktivasi', ['nrp' => 'x', 'token' => 'SALAH', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertStatus(429)->assertJsonPath('code', 'too_many_attempts');
    }
}
