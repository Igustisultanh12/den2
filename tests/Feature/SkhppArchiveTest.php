<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LetterLog;
use App\Models\Skhpp;
use App\Models\SkhppArchive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Arsip & Nomor SKHPP terpisah dari Agenda/Arsip Surat, terintegrasi dengan
 * menu Penerbitan SKHPP, dan penomoran SKHPP-D / SKHPP-P bersambung.
 */
class SkhppArchiveTest extends TestCase
{
    use RefreshDatabase;

    private int $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::fake(); // WA gateway & QR
        Storage::fake('local');
        Storage::fake('public');
        $this->year = (int) date('Y');
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'is_active' => true, 'must_change_password' => false, 'mfa_enabled' => false], $attrs));
    }

    private function skhpp(User $by, string $kategori = 'militer', array $attrs = []): Skhpp
    {
        $s = Skhpp::forceCreate(array_merge([
            'kategori_personel' => $kategori,
            'is_pernikahan' => false,
            'surat_pengantar' => 'Surat pengantar',
            'nama' => 'Serka ' . Str::random(5),
            'jabatan_pekerjaan' => 'Staf',
            'tempat_lahir' => 'Surabaya',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'Laki-laki',
            'agama' => 'Islam',
            'alamat' => 'Koarmada 2',
            'peruntukan' => 'Pendidikan',
            'status' => 'pending',
            'verification_code' => 'SKHPP-' . Str::upper(Str::random(10)),
            'submitted_by' => $by->id,
            'submitted_at' => now(),
        ], $attrs));
        SkhppArchive::syncFromSkhpp($s); // sama seperti SkhppController@store

        return $s;
    }

    private function requestManual(User $as, string $jenis = 'D', array $extra = [])
    {
        return $this->actingAs($as)->post(route('skhpp-archives.store'), array_merge([
            'jenis' => $jenis,
            'priority' => 'R',
            'tanggal' => now()->toDateString(),
            'perihal' => 'SKHPP manual di luar SINDEN',
        ], $extra));
    }

    public function test_menu_skhpp_and_manual_numbers_share_one_sequence_per_type(): void
    {
        $komandan = $this->user('admin');
        $operator = $this->user('personel', ['feature_access' => ['skhpp', 'skhpp_archive']]);

        // 1) Generate SKHPP dari menu -> langsung masuk arsip, menunggu TTE.
        $fromMenu = $this->skhpp($operator);
        $archive = SkhppArchive::where('skhpp_id', $fromMenu->id)->firstOrFail();
        $this->assertSame('sinden', $archive->sumber);
        $this->assertSame(SkhppArchive::STATUS_MENUNGGU_PDF, $archive->status);
        $this->assertNull($archive->nomor_skhpp);

        // 2) Nomor manual SKHPP-D pertama -> 1.
        $this->requestManual($operator, 'D')->assertSessionHasNoErrors()->assertSessionHas('success');
        $manual1 = SkhppArchive::where('sumber', 'manual')->latest('id')->first();
        $this->assertSame(1, $manual1->nomor_urut);
        $roman = \App\Services\SkhppNumberService::romanMonth((int) date('n'));
        $this->assertSame("R / 1 / SKHPP / {$roman} / {$this->year}", $manual1->nomor_skhpp);

        // 3) TTE Komandan terbit -> nomor menu melanjutkan (2) & PDF otomatis tersimpan.
        $this->actingAs($komandan)->post(route('skhpp.approve', $fromMenu->id), ['priority' => 'R'])->assertSessionHas('success');
        $fromMenu->refresh();
        $archive->refresh();
        $this->assertSame('approved', $fromMenu->status);
        $this->assertSame(2, (int) $fromMenu->nomor_urut);
        $this->assertSame($fromMenu->nomor_skhpp, $archive->nomor_skhpp);
        $this->assertSame(SkhppArchive::STATUS_TERSIMPAN, $archive->status);
        $this->assertNotNull($archive->file_path);
        Storage::disk('local')->assertExists($archive->file_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($archive->file_path));

        // 4) Manual berikutnya melanjutkan menu (3); SKHPP-P berurutan sendiri (1).
        $this->requestManual($operator, 'D');
        $this->assertSame(3, SkhppArchive::where('sumber', 'manual')->where('jenis', 'D')->max('nomor_urut'));
        $this->requestManual($operator, 'P');
        $this->assertSame(1, (int) SkhppArchive::where('jenis', 'P')->max('nomor_urut'));

        // 5) SKHPP-P dari menu melanjutkan SKHPP-P manual (2), tidak terpengaruh D.
        $pMenu = $this->skhpp($operator, 'perusahaan');
        $this->actingAs($komandan)->post(route('skhpp.approve', $pMenu->id));
        $this->assertSame(2, (int) $pMenu->fresh()->nomor_urut);

        // 6) Tidak lagi tercatat di Agenda Surat.
        $this->assertSame(0, LetterLog::count());
    }

    public function test_upload_view_and_number_preview(): void
    {
        $operator = $this->user('personel', ['feature_access' => ['skhpp', 'skhpp_archive']]);
        $this->requestManual($operator, 'D', ['nama' => 'Budi']);
        $archive = SkhppArchive::latest('id')->first();

        $this->actingAs($operator)->getJson(route('skhpp-archives.next-number', ['jenis' => 'D']))
            ->assertOk()->assertJsonPath('sequence', 2);

        $this->actingAs($operator)->post(route('skhpp-archives.upload', $archive), [
            'file' => UploadedFile::fake()->create('skhpp.pdf', 50, 'application/pdf'),
        ])->assertSessionHas('success');

        $archive->refresh();
        $this->assertSame(SkhppArchive::STATUS_TERSIMPAN, $archive->status);
        Storage::disk('local')->assertExists($archive->file_path);
        $this->actingAs($operator)->get(route('skhpp-archives.file', $archive))->assertOk();

        // Hanya PDF.
        $this->actingAs($operator)->post(route('skhpp-archives.upload', $archive), [
            'file' => UploadedFile::fake()->create('skhpp.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertSessionHasErrors('file');
    }

    public function test_custom_number_is_admin_only_and_must_be_free(): void
    {
        $admin = $this->user('admin');
        $operator = $this->user('personel', ['feature_access' => ['skhpp', 'skhpp_archive']]);

        $this->requestManual($operator, 'D', ['custom_nomor_urut' => 50])->assertSessionHas('error');
        $this->assertSame(0, SkhppArchive::count());

        $this->requestManual($admin, 'D', ['custom_nomor_urut' => 50])->assertSessionHas('success');
        $this->requestManual($admin, 'D', ['custom_nomor_urut' => 50])->assertSessionHas('error');
        // Nomor otomatis berikutnya melanjutkan dari nomor tertinggi.
        $this->requestManual($operator, 'D');
        $this->assertSame(51, (int) SkhppArchive::max('nomor_urut'));
    }

    public function test_skhpp_is_always_rahasia_and_supports_inserted_numbers_with_letters(): void
    {
        $admin = $this->user('admin');
        $operator = $this->user('personel', ['feature_access' => ['skhpp_archive']]);
        $roman = \App\Services\SkhppNumberService::romanMonth((int) date('n'));

        // Sifat yang dikirim selain R diabaikan: SKHPP selalu RAHASIA.
        $this->requestManual($operator, 'D', ['priority' => 'B']);
        $this->assertSame("R / 1 / SKHPP / {$roman} / {$this->year}", SkhppArchive::latest('id')->value('nomor_skhpp'));
        $this->requestManual($operator, 'D');                       // 2

        // Personel boleh nomor sisipan dari nomor yang sudah terbit.
        $this->requestManual($operator, 'D', ['custom_nomor_urut' => '1A'])->assertSessionHas('success');
        $sisipan = SkhppArchive::latest('id')->first();
        $this->assertSame("R / 1a / SKHPP / {$roman} / {$this->year}", $sisipan->nomor_skhpp);
        $this->assertSame(1, $sisipan->nomor_urut);
        $this->assertSame('a', $sisipan->nomor_sisipan);
        $this->assertSame('1a', $sisipan->nomor_urut_label);

        // Sisipan yang sama tidak boleh dipakai dua kali.
        $this->requestManual($operator, 'D', ['custom_nomor_urut' => '1a'])->assertSessionHas('error');
        // Personel tidak boleh meloncati nomor / sisipan dari nomor yang belum terbit.
        $this->requestManual($operator, 'D', ['custom_nomor_urut' => '50'])->assertSessionHas('error');
        $this->requestManual($operator, 'D', ['custom_nomor_urut' => '9a'])->assertSessionHas('error');
        // Format tidak valid ditolak.
        $this->requestManual($operator, 'D', ['custom_nomor_urut' => 'a1'])->assertSessionHasErrors('custom_nomor_urut');

        // Nomor otomatis tetap lanjut dari angka terbesar (sisipan tidak menggeser urutan).
        $this->requestManual($operator, 'D');
        $this->assertSame(3, (int) SkhppArchive::latest('id')->value('nomor_urut'));

        // Menu SINDEN: Komandan menyetujui dengan nomor sisipan & sifat lain diabaikan.
        $s = $this->skhpp($admin);
        $this->actingAs($admin)->post(route('skhpp.approve', $s->id), ['priority' => 'K', 'custom_nomor_urut' => '2b'])->assertSessionHas('success');
        $s->refresh();
        $this->assertSame("R / 2b / SKHPP / {$roman} / {$this->year}", $s->nomor_skhpp);
        $this->assertSame('b', $s->nomor_sisipan);
        $this->assertSame('b', SkhppArchive::where('skhpp_id', $s->id)->value('nomor_sisipan'));

        // Atur nomor (Admin) juga menerima sisipan & menolak yang sudah dipakai.
        $s2 = $this->skhpp($admin);
        $this->actingAs($admin)->post(route('skhpp.update-number', $s2->id), ['custom_nomor_urut' => '2b'])->assertSessionHas('error');
        $this->actingAs($admin)->post(route('skhpp.update-number', $s2->id), ['custom_nomor_urut' => '2c'])->assertSessionHas('success');
        $this->assertSame("R / 2c / SKHPP / {$roman} / {$this->year}", $s2->fresh()->nomor_skhpp);
    }

    public function test_sub_categories_dik_and_nikah_are_kept_per_skhpp_type(): void
    {
        $admin = $this->user('admin');
        $operator = $this->user('personel', ['feature_access' => ['skhpp_archive']]);
        $catD = Category::forceCreate(['name' => 'SKHPP Dinas', 'code' => 'SKHPP-D', 'start_number' => 1]);
        $catP = Category::forceCreate(['name' => 'SKHPP Mitra', 'code' => 'SKHPP-P', 'start_number' => 1]);
        $dik = \App\Models\SubCategory::forceCreate(['category_id' => $catD->id, 'name' => 'Dik']);
        $nikah = \App\Models\SubCategory::forceCreate(['category_id' => $catD->id, 'name' => 'Nikah']);
        $proyek = \App\Models\SubCategory::forceCreate(['category_id' => $catP->id, 'name' => 'Proyek']);

        // Halaman arsip mengirim pilihan sub-kategori per jenis.
        $this->actingAs($operator)->get(route('skhpp-archives.index'))
            ->assertInertia(fn ($page) => $page
                ->where('subCategories.D', fn ($s) => collect($s)->pluck('name')->sort()->values()->all() === ['Dik', 'Nikah'])
                ->where('subCategories.P', fn ($s) => collect($s)->pluck('name')->all() === ['Proyek']));

        // Manual dengan sub-kategori SKHPP-D Dik.
        $this->requestManual($operator, 'D', ['sub_category_id' => $dik->id])->assertSessionHas('success');
        $this->assertSame($dik->id, (int) SkhppArchive::latest('id')->value('sub_category_id'));
        // Sub-kategori SKHPP-P tidak boleh dipakai untuk SKHPP-D.
        $this->requestManual($operator, 'D', ['sub_category_id' => $proyek->id])->assertSessionHas('error');

        // Sub-kategori tidak memisahkan urutan: Dik & tanpa sub tetap satu urutan SKHPP-D.
        $this->requestManual($operator, 'D');
        $this->assertSame(2, (int) SkhppArchive::latest('id')->value('nomor_urut'));

        // SKHPP dari menu: pengajuan pernikahan otomatis "Nikah"; peruntukan pendidikan => "Dik".
        $nikahSkhpp = $this->skhpp($admin, 'militer', ['is_pernikahan' => true, 'peruntukan' => 'Pengajuan nikah dinas']);
        $this->assertSame($nikah->id, (int) SkhppArchive::where('skhpp_id', $nikahSkhpp->id)->value('sub_category_id'));

        $dikSkhpp = $this->skhpp($admin); // peruntukan "Pendidikan"
        $this->assertSame($dik->id, (int) SkhppArchive::where('skhpp_id', $dikSkhpp->id)->value('sub_category_id'));

        // Koreksi sub-kategori di arsip (juga untuk SKHPP SINDEN).
        $archive = SkhppArchive::where('skhpp_id', $dikSkhpp->id)->first();
        $this->actingAs($admin)->put(route('skhpp-archives.update', $archive), ['sub_category_id' => $nikah->id])->assertSessionHas('success');
        $this->assertSame($nikah->id, (int) $archive->fresh()->sub_category_id);
        // Sinkron ulang dari menu tidak menimpa pilihan yang valid.
        SkhppArchive::syncFromSkhpp($dikSkhpp->fresh());
        $this->assertSame($nikah->id, (int) $archive->fresh()->sub_category_id);

        // Filter per sub-kategori.
        $this->actingAs($admin)->get(route('skhpp-archives.index', ['sub_category_id' => $dik->id]))
            ->assertInertia(fn ($page) => $page->where('archives.data', fn ($rows) => collect($rows)->pluck('sub_category_id')->unique()->values()->all() === [$dik->id]));

        // API mobile ikut mengirim sub-kategori.
        $token = $operator->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/mobile/skhpp-archives?jenis=D')
            ->assertOk()
            ->assertJsonPath('sub_categories.P.0.name', 'Proyek');
        $this->withToken($token)->postJson('/api/mobile/skhpp-archives', [
            'jenis' => 'D', 'sub_category_id' => $nikah->id, 'tanggal' => now()->toDateString(), 'perihal' => 'dari HP',
        ])->assertOk();
        $this->assertSame('Nikah', SkhppArchive::latest('id')->first()->subCategory->name);
    }

    public function test_deleting_numbered_skhpp_marks_archive_revoked_and_number_is_not_reused(): void
    {
        $admin = $this->user('admin');
        $s = $this->skhpp($admin);
        $this->actingAs($admin)->post(route('skhpp.approve', $s->id));
        $archive = SkhppArchive::where('skhpp_id', $s->id)->first();
        $this->assertSame(1, $archive->nomor_urut);

        // SKHPP belum bernomor -> arsipnya ikut terhapus.
        $draft = $this->skhpp($admin);
        $this->actingAs($admin)->delete(route('skhpp.destroy', $draft->id));
        $this->assertDatabaseMissing('skhpp_archives', ['skhpp_id' => $draft->id]);

        $this->actingAs($admin)->delete(route('skhpp.destroy', $s->id));
        $archive->refresh();
        $this->assertSame(SkhppArchive::STATUS_DICABUT, $archive->status);
        $this->assertNull($archive->skhpp_id);

        $this->requestManual($admin, 'D');
        $this->assertSame(2, (int) SkhppArchive::where('sumber', 'manual')->value('nomor_urut'));
    }

    public function test_skhpp_is_separated_from_letter_agenda_and_archive(): void
    {
        $admin = $this->user('admin');
        $skhppCat = Category::forceCreate(['name' => 'SKHPP Dinas Militer & PNS', 'code' => 'SKHPP-D', 'start_number' => 1]);
        $suratCat = Category::forceCreate(['name' => 'Surat Biasa', 'code' => 'B', 'start_number' => 1]);

        // Nomor SKHPP lama dari Agenda Surat tetap dihitung agar urutan berlanjut.
        LetterLog::forceCreate(['full_number' => "R / 7 / SKHPP / I / {$this->year}", 'sequence' => '7', 'priority' => 'R', 'category_id' => $skhppCat->id, 'subject' => 'lama', 'date' => "{$this->year}-01-10", 'is_archived' => false]);
        LetterLog::forceCreate(['full_number' => "B / 3 / B / I / {$this->year}", 'sequence' => '3', 'priority' => 'B', 'category_id' => $suratCat->id, 'subject' => 'surat', 'date' => "{$this->year}-01-10", 'is_archived' => false]);

        $this->assertSame(8, \App\Services\SkhppNumberService::nextSequence('D', $this->year));

        // Agenda Surat tidak lagi menampilkan / menerima kategori SKHPP.
        $this->actingAs($admin)->get(route('letter-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('categories', fn ($c) => collect($c)->pluck('code')->doesntContain('SKHPP-D'))
                ->where('logs.data', fn ($logs) => collect($logs)->pluck('subject')->all() === ['surat']));

        $this->actingAs($admin)->post(route('letter-logs.store'), [
            'category_id' => $skhppCat->id, 'priority' => 'R', 'subject' => 'x', 'date' => now()->toDateString(), 'sequence' => '9',
        ])->assertSessionHas('error');
        $this->assertSame(2, LetterLog::count());

        $this->actingAs($admin)->get(route('letters.index'))
            ->assertInertia(fn ($page) => $page->where('categories', fn ($c) => collect($c)->pluck('code')->doesntContain('SKHPP-D')));
    }

    public function test_migration_moves_legacy_skhpp_numbers_and_pdfs_into_archive(): void
    {
        $admin = $this->user('admin');
        $catD = Category::forceCreate(['name' => 'SKHPP Dinas', 'code' => 'SKHPP-D', 'start_number' => 1]);
        $catP = Category::forceCreate(['name' => 'SKHPP Mitra', 'code' => 'SKHPP-P', 'start_number' => 1]);

        // SKHPP SINDEN lama yang sudah disahkan & tersinkron ke Agenda Surat.
        $s = Skhpp::forceCreate([
            'kategori_personel' => 'militer', 'is_pernikahan' => false, 'surat_pengantar' => 'x', 'nama' => 'Lama Satu',
            'jabatan_pekerjaan' => 'Staf', 'tempat_lahir' => 'Sby', 'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'Laki-laki',
            'agama' => 'Islam', 'alamat' => 'x', 'peruntukan' => 'Dinas', 'status' => 'approved', 'verification_code' => 'SKHPP-LAMA1',
            'nomor_skhpp' => 'R / 4 / SKHPP / III / 2026', 'nomor_urut' => 4, 'bulan_romawi' => 'III', 'tahun' => 2026, 'submitted_by' => $admin->id,
        ]);
        LetterLog::forceCreate(['full_number' => 'R / 4 / SKHPP / III / 2026', 'sequence' => '4', 'priority' => 'R', 'category_id' => $catD->id, 'subject' => 'SKHPP - Lama Satu', 'date' => '2026-03-02', 'is_archived' => true]);
        // Nomor manual lama (dibooking di Agenda) + PDF-nya di Arsip Surat.
        LetterLog::forceCreate(['full_number' => 'B / 5 / SKHPP / III / 2026', 'sequence' => '5', 'priority' => 'B', 'category_id' => $catD->id, 'subject' => 'SKHPP manual lama', 'recipient' => 'Asintel', 'date' => '2026-03-05', 'is_archived' => true]);
        LetterLog::forceCreate(['full_number' => 'R / 2 / SKHPP / IV / 2026', 'sequence' => '2', 'priority' => 'R', 'category_id' => $catP->id, 'subject' => 'SKHPP-P lama', 'date' => '2026-04-01', 'is_archived' => false]);
        \App\Models\Letter::forceCreate(['type' => 'keluar', 'security_level' => 'biasa', 'category_id' => $catD->id, 'letter_number' => 'B / 5 / SKHPP / III / 2026', 'subject' => 'SKHPP manual lama', 'date' => '2026-03-05', 'issuer' => 'x', 'file_path' => 'archives/lama.pdf']);

        $sub = \App\Models\SubCategory::forceCreate(['category_id' => $catD->id, 'name' => 'Dik']);
        LetterLog::where('full_number', 'B / 5 / SKHPP / III / 2026')->update(['sub_category_id' => $sub->id]);

        SkhppArchive::query()->delete();
        (require database_path('migrations/2026_11_02_110000_create_skhpp_archives_table.php'))->up();
        (require database_path('migrations/2026_11_02_120000_add_nomor_sisipan_to_skhpp_tables.php'))->up();
        (require database_path('migrations/2026_11_02_130000_add_sub_category_to_skhpp_archives_table.php'))->up();
        $this->assertSame($sub->id, (int) SkhppArchive::where('nomor_skhpp', 'B / 5 / SKHPP / III / 2026')->value('sub_category_id'), 'Sub-kategori lama dari Agenda Surat ikut terbawa');

        $this->assertSame(3, SkhppArchive::count(), 'SKHPP SINDEN tidak terduplikasi dari Agenda Surat');

        $sinden = SkhppArchive::where('skhpp_id', $s->id)->first();
        $this->assertSame('sinden', $sinden->sumber);
        $this->assertSame(4, $sinden->nomor_urut);

        $manual = SkhppArchive::where('nomor_skhpp', 'B / 5 / SKHPP / III / 2026')->first();
        $this->assertSame('manual', $manual->sumber);
        $this->assertSame('D', $manual->jenis);
        $this->assertSame('B', $manual->priority);
        $this->assertSame(SkhppArchive::STATUS_TERSIMPAN, $manual->status);
        $this->assertSame('public', $manual->file_disk);
        $this->assertSame('archives/lama.pdf', $manual->file_path);

        $p = SkhppArchive::where('jenis', 'P')->first();
        $this->assertSame(2, $p->nomor_urut);
        $this->assertSame(SkhppArchive::STATUS_MENUNGGU_PDF, $p->status);

        $this->assertSame(6, \App\Services\SkhppNumberService::nextSequence('D', 2026));
        $this->assertSame(3, \App\Services\SkhppNumberService::nextSequence('P', 2026));
    }

    public function test_mobile_api_lists_requests_uploads_and_streams_archive(): void
    {
        $operator = $this->user('personel', ['feature_access' => ['skhpp_archive']]);
        $token = $operator->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/mobile/skhpp-archives', [
            'jenis' => 'P', 'priority' => 'B', 'tanggal' => now()->toDateString(), 'perihal' => 'SKHPP mitra dari HP',
        ])->assertOk()->assertJsonPath('status', 'success');

        $archive = SkhppArchive::latest('id')->first();
        $this->assertSame('P', $archive->jenis);
        $this->assertSame(1, $archive->nomor_urut);

        $this->withToken($token)->getJson('/api/mobile/skhpp-archives?jenis=P')
            ->assertOk()
            ->assertJsonPath('data.0.nomor_skhpp', $archive->nomor_skhpp)
            ->assertJsonPath('data.0.status', 'menunggu_pdf')
            ->assertJsonPath('summary.P.next', 2);

        // Validasi dikembalikan sebagai JSON 422.
        $this->withToken($token)->postJson('/api/mobile/skhpp-archives', ['jenis' => 'X'])->assertStatus(422);

        $this->withToken($token)->post("/api/mobile/skhpp-archives/{$archive->id}/upload", [
            'file' => UploadedFile::fake()->create('skhpp.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame(SkhppArchive::STATUS_TERSIMPAN, $archive->fresh()->status);

        $this->withToken($token)->get("/api/mobile/pdf/skhpp-archive/{$archive->id}")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Tanpa fitur Arsip SKHPP: ditolak.
        $other = $this->user('personel');
        $this->withToken($other->createToken('t')->plainTextToken)->getJson('/api/mobile/skhpp-archives')->assertForbidden();
        $this->assertNotContains('skhpp_archive.view', \App\Support\MobileAccess::grantedAbilities($other));
    }

    public function test_personel_without_archive_feature_is_blocked(): void
    {
        $pers = $this->user('personel', ['feature_access' => ['skhpp']]);
        $this->actingAs($pers)->get(route('skhpp-archives.index'))->assertForbidden();
        $this->actingAs($pers)->post(route('skhpp-archives.store'), [])->assertForbidden();
    }
}
