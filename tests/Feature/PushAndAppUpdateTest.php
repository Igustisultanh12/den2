<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\LiveChatMessage;
use App\Models\LiveChatThread;
use App\Models\Setting;
use App\Models\User;
use App\Support\ApkInspector;
use App\Support\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Notifikasi push Firebase & pembaruan aplikasi Android dari APK yang diunggah
 * di Pengaturan web (diporting dari SISINDEN Kodaeral V).
 */
class PushAndAppUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role = 'personel', array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role, 'is_active' => true, 'must_change_password' => false, 'mfa_enabled' => false,
        ], $attrs));
    }

    /** Kunci RSA uji; PHP Windows memerlukan openssl.cnf eksplisit (Linux tidak). */
    private function rsaPrivateKeyPem(): string
    {
        $opts = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        if (PHP_OS_FAMILY === 'Windows' && is_file($cnf)) {
            $opts['config'] = $cnf;
        }
        $key = openssl_pkey_new($opts);
        openssl_pkey_export($key, $pem, null, $opts);
        return $pem;
    }

    private function configurePush($fcmResponse = null): void
    {
        $pem = $this->rsaPrivateKeyPem();
        $this->assertNull(PushNotifier::storeServiceAccount(json_encode([
            'type' => 'service_account', 'project_id' => 'sisinden-test', 'client_email' => 'fcm@sisinden-test.iam.gserviceaccount.com',
            'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token',
        ])));
        $cfg = PushNotifier::parseGoogleServices(json_encode([
            'project_info' => ['project_number' => '123456789', 'project_id' => 'sisinden-test'],
            'client' => [[
                'client_info' => ['mobilesdk_app_id' => '1:123456789:android:abc', 'android_client_info' => ['package_name' => 'id.my.sisinden.app']],
                'api_key' => [['current_key' => 'AIzaPUBLICKEY']],
            ]],
        ]));
        $this->assertIsArray($cfg);
        Setting::updateOrCreate(['key' => 'fcm_client_config'], ['value' => json_encode($cfg)]);
        Cache::forget('fcm:access_token');
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => $fcmResponse ?? Http::response(['name' => 'projects/sisinden-test/messages/1']),
        ]);
    }

    /** @return array<int, array{token: string, data: array}> */
    private function pushesSent(): array
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'fcm.googleapis.com'))
            ->map(fn ($pair) => ['token' => $pair[0]['message']['token'], 'data' => $pair[0]['message']['data']])
            ->values()->all();
    }

    public function test_firebase_settings_are_encrypted_and_never_shared(): void
    {
        $this->getJson('/api/mobile/config')->assertOk()->assertJsonPath('push', null)->assertJsonPath('push_enabled', false);

        $this->configurePush();
        $cfg = $this->getJson('/api/mobile/config')->assertOk()
            ->assertJsonPath('push.project_id', 'sisinden-test')
            ->assertJsonPath('push.sender_id', '123456789')
            ->assertJsonPath('push_enabled', true);
        $this->assertStringNotContainsString('PRIVATE KEY', $cfg->getContent());
        $this->assertStringNotContainsString('PRIVATE KEY', (string) Setting::where('key', 'fcm_service_account')->value('value'), 'service account harus terenkripsi');

        Setting::updateOrCreate(['key' => 'google_client_secret'], ['value' => 'rahasia-google']);
        Setting::updateOrCreate(['key' => 'agora_app_certificate'], ['value' => 'rahasia-agora']);
        $public = HandleInertiaRequests::publicSettings(Setting::pluck('value', 'key')->toArray());
        foreach (['fcm_service_account', 'fcm_client_config', 'google_client_secret', 'agora_app_certificate'] as $secret) {
            $this->assertArrayNotHasKey($secret, $public);
        }
        // Halaman login (tamu) tidak boleh memuat rahasia apa pun.
        $login = $this->get('/login')->assertOk()->getContent();
        $this->assertStringNotContainsString('rahasia-google', $login);
        $this->assertStringNotContainsString('rahasia-agora', $login);

        // google-services.json untuk paket lain ditolak.
        $this->assertIsString(PushNotifier::parseGoogleServices(json_encode(['project_info' => ['project_id' => 'x'], 'client' => [['client_info' => ['android_client_info' => ['package_name' => 'com.lain']]]]])));
    }

    public function test_super_admin_saves_firebase_files_from_settings_page(): void
    {
        $pem = $this->rsaPrivateKeyPem();
        $gs = json_encode(['project_info' => ['project_number' => '42', 'project_id' => 'koarmada2'], 'client' => [[
            'client_info' => ['mobilesdk_app_id' => '1:42:android:x', 'android_client_info' => ['package_name' => 'id.my.sisinden.app']],
            'api_key' => [['current_key' => 'AIzaX']],
        ]]]);
        $sa = json_encode(['type' => 'service_account', 'project_id' => 'koarmada2', 'client_email' => 'a@koarmada2.iam.gserviceaccount.com', 'private_key' => $pem]);

        $this->actingAs($this->user('super_admin'))
            ->put(route('settings.update'), ['agency_name' => 'DETASEMEN INTELIJEN KOARMADA 2', 'start_number' => 1, 'fcm_enabled' => '1', 'fcm_google_services' => $gs, 'fcm_service_account' => $sa])
            ->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame('koarmada2', PushNotifier::serviceAccountProject());
        $this->assertSame('koarmada2', PushNotifier::clientConfig()['project_id']);
        $this->assertTrue(PushNotifier::enabled());

        $this->actingAs($this->user('super_admin'))
            ->put(route('settings.update'), ['agency_name' => 'X', 'start_number' => 1, 'fcm_service_account' => '{"type":"bukan"}'])
            ->assertSessionHas('error');
    }

    public function test_device_registration_test_push_and_logout(): void
    {
        $this->postJson('/api/mobile/push/register', ['token' => str_repeat('x', 40)])->assertStatus(401);
        $this->configurePush();

        $u = $this->user();
        $token = 'fcm-token-' . str_repeat('a', 30);
        $this->withToken($u->createToken('t')->plainTextToken)->postJson('/api/mobile/push/register', ['token' => $token, 'app_version' => '1.3.0'])
            ->assertOk()->assertJsonPath('push_enabled', true);
        $this->assertSame(1, DeviceToken::where('user_id', $u->id)->count());

        // Token yang sama dipindah ke akun lain (satu token = satu pemilik).
        $v = $this->user();
        $tv = $v->createToken('t')->plainTextToken;
        $this->withToken($tv)->postJson('/api/mobile/push/register', ['token' => $token])->assertOk();
        $this->assertSame(0, DeviceToken::where('user_id', $u->id)->count());

        $this->withToken($tv)->postJson('/api/mobile/push/test')->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame($token, $this->pushesSent()[0]['token']);

        // Keluar → token perangkat dihapus.
        $this->withToken($tv)->postJson('/api/mobile/logout', ['push_token' => $token])->assertOk();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_push_sent_for_notifications_and_chat(): void
    {
        $this->configurePush();
        $personel = $this->user('personel', ['name' => 'Bambang']);
        $super = $this->user('super_admin', ['name' => 'Operator']);
        $pasops = $this->user('pasops');
        DeviceToken::register($personel->id, 'tok-personel-' . str_repeat('p', 20));
        DeviceToken::register($super->id, 'tok-super-' . str_repeat('q', 20));
        DeviceToken::register($pasops->id, 'tok-pasops-' . str_repeat('r', 20));

        // Warta berbasis role → hanya pasops.
        AppNotification::notify(null, 'pasops', 'Pengajuan TTE SP Jaga', 'Mohon TTE', 'warning', '/sp-jaga');
        $this->get('/login'); // siklus request menjalankan pengiriman yang ditunda (defer)
        $sent = $this->pushesSent();
        $this->assertCount(1, $sent);
        $this->assertStringStartsWith('tok-pasops-', $sent[0]['token']);
        $this->assertSame('/sp-jaga', $sent[0]['data']['link']);

        // Pesan personel di Chat Dinas → operator (Super Admin) menerima push, pengirim tidak.
        $thread = LiveChatThread::create(['uuid' => (string) Str::uuid(), 'user_id' => $personel->id]);
        LiveChatMessage::create(['thread_id' => $thread->id, 'sender_type' => 'USER', 'sender_id' => $personel->id, 'sender_name' => 'Bambang', 'message' => 'Izin lapor']);
        $this->get('/login');
        $chat = collect($this->pushesSent())->firstWhere('data.type', 'chat');
        $this->assertNotNull($chat);
        $this->assertStringStartsWith('tok-super-', $chat['token']);
        $this->assertSame('Izin lapor', $chat['data']['body']);
    }

    public function test_unregistered_fcm_token_is_removed(): void
    {
        $this->configurePush(Http::response(['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404));
        $u = $this->user();
        DeviceToken::register($u->id, 'tok-lama-' . str_repeat('z', 20));
        $this->assertSame(0, PushNotifier::sendNow([$u->id], ['type' => 'notification']));
        $this->assertSame(0, DeviceToken::count());
    }

    private function fakeApk(string $dir, string $name, ?string $manifest): string
    {
        $path = $dir . '/' . $name;
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($manifest !== null) {
            $zip->addFromString('AndroidManifest.xml', $manifest);
        }
        $zip->addFromString('classes.dex', str_repeat('dex', 700));
        $zip->close();
        return $path;
    }

    public function test_apk_uploaded_in_settings_is_served_to_app(): void
    {
        Storage::fake('local');
        $manifest = file_get_contents(base_path('tests/Fixtures/AndroidManifest-1.5.0.bin'));
        $this->assertSame(['package' => 'id.my.sisinden.app', 'version_name' => '1.5.0', 'version_code' => 13], ApkInspector::parseManifest($manifest));

        $this->getJson('/api/mobile/app-update')->assertOk()->assertJsonPath('available', false);
        $this->get('/api/mobile/app-update/download')->assertStatus(404);

        $dir = sys_get_temp_dir() . '/apk-' . uniqid();
        mkdir($dir);
        $apk = $this->fakeApk($dir, 'SISINDEN-1.5.0.apk', $manifest);
        $upload = fn ($path, $name) => new UploadedFile($path, $name, 'application/vnd.android.package-archive', null, true);

        // Hanya Super Admin (grup Pengaturan) yang boleh mengunggah.
        $this->actingAs($this->user('personel'))->post('/settings/app-update', ['apk' => $upload($apk, 'SISINDEN-1.5.0.apk')])->assertForbidden();
        $this->actingAs($this->user('admin'))->post('/settings/app-update', ['apk' => $upload($apk, 'SISINDEN-1.5.0.apk')])->assertForbidden();

        $super = $this->user('super_admin');
        $this->configurePush();
        DeviceToken::register($this->user()->id, 'tok-hp-' . str_repeat('h', 20));

        // Bukan APK SISINDEN → ditolak.
        $this->actingAs($super)->post('/settings/app-update', ['apk' => $upload($this->fakeApk($dir, 'lain.apk', null), 'lain.apk')])
            ->assertSessionHas('error');
        $this->actingAs($super)->post('/settings/app-update', ['apk' => $upload($apk, 'SISINDEN-1.5.0.apk'), 'notes' => 'Pelacakan lokasi lebih hemat', 'notify' => '1'])
            ->assertSessionHas('success');

        $meta = $this->getJson('/api/mobile/app-update')->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('version', '1.5.0')
            ->assertJsonPath('version_code', 13)
            ->assertJsonPath('notes', 'Pelacakan lokasi lebih hemat')
            ->json();
        $this->assertSame(hash_file('sha256', $apk), $meta['sha256']);
        $this->assertStringNotContainsString('app-updates/', json_encode($meta), 'lokasi berkas server tidak dibocorkan');

        $dl = $this->get('/api/mobile/app-update/download')->assertOk();
        $this->assertSame('application/vnd.android.package-archive', $dl->headers->get('Content-Type'));

        $push = collect($this->pushesSent())->firstWhere('data.type', 'app_update');
        $this->assertNotNull($push);
        $this->assertSame('1.5.0', $push['data']['version']);

        // Halaman Pengaturan menampilkan ringkasan; metadata tidak dibagikan ke halaman lain.
        $this->assertArrayNotHasKey('app_update_meta', HandleInertiaRequests::publicSettings(Setting::pluck('value', 'key')->toArray()));

        // Versi lebih lama ditolak.
        $old = str_replace(mb_convert_encoding('1.5.0', 'UTF-16LE'), mb_convert_encoding('1.4.0', 'UTF-16LE'), $manifest);
        $this->actingAs($super)->post('/settings/app-update', ['apk' => $upload($this->fakeApk($dir, 'old.apk', $old), 'old.apk')])
            ->assertSessionHas('error');
        $this->getJson('/api/mobile/app-update')->assertJsonPath('version', '1.5.0');

        $this->actingAs($super)->delete('/settings/app-update')->assertSessionHas('success');
        $this->getJson('/api/mobile/app-update')->assertJsonPath('available', false);
        $this->assertSame([], Storage::disk('local')->allFiles('app-updates'));
    }
}
