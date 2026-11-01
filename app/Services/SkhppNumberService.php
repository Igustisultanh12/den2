<?php

namespace App\Services;

use App\Models\Category;
use App\Models\LetterLog;
use App\Models\Skhpp;
use App\Models\SkhppArchive;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran SKHPP tunggal untuk menu Penerbitan SKHPP (SINDEN) dan permintaan
 * nomor manual. Aturan:
 *   - SKHPP-D (Dinas Militer & PNS) dan SKHPP-P (Mitra/Perusahaan) punya urutan sendiri.
 *   - Urutan berlanjut per tahun; nomor manual melanjutkan nomor SINDEN dan sebaliknya.
 *   - Sifat SKHPP selalu RAHASIA (R).
 *   - Format: R / {urut} / SKHPP / {bulan romawi} / {tahun}  (contoh: R / 171 / SKHPP / VIII / 2026)
 */
class SkhppNumberService
{
    public const ROMAN = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    /** SKHPP selalu bersifat RAHASIA. */
    public const PRIORITY = 'R';
    public const PRIORITIES = ['R' => 'RAHASIA'];

    public static function jenisFor(?string $kategoriPersonel): string
    {
        return $kategoriPersonel === 'perusahaan' ? 'P' : 'D';
    }

    public static function romanMonth(int $month): string
    {
        return self::ROMAN[$month] ?? 'I';
    }

    /** @param int|string $sequence angka urut atau label sisipan (mis. "12a") */
    public static function format(string $priority, int|string $sequence, string $romanMonth, int $year): string
    {
        return "{$priority} / {$sequence} / SKHPP / {$romanMonth} / {$year}";
    }

    /**
     * Uraikan nomor urut ketikan petugas: "12" => [12, null], "12A" => [12, "a"].
     * Mengembalikan null bila formatnya tidak valid.
     *
     * @return array{0:int,1:?string}|null
     */
    public static function parse(int|string|null $input): ?array
    {
        if (!preg_match('/^\s*0*(\d{1,6})\s*([a-zA-Z]{1,3})?\s*$/', (string) $input, $m) || (int) $m[1] < 1) {
            return null;
        }
        $suffix = isset($m[2]) && $m[2] !== '' ? strtolower($m[2]) : null;

        return [(int) $m[1], $suffix];
    }

    /** "12" atau "12a" */
    public static function label(int $sequence, ?string $suffix): string
    {
        return $sequence . ($suffix ?? '');
    }

    /** Aturan validasi nomor urut (angka, boleh diikuti 1–3 huruf sisipan). */
    public const SEQUENCE_RULE = 'regex:/^\s*\d{1,6}\s*[a-zA-Z]{0,3}\s*$/';

    /** Kategori surat SKHPP-D / SKHPP-P (dikelola di menu Kategori Surat). */
    public static function categoryIds(string $jenis): \Illuminate\Support\Collection
    {
        return Category::skhpp()->get(['id', 'code'])
            ->filter(fn ($c) => (str_contains(strtoupper($c->code), '-P') ? 'P' : 'D') === $jenis)
            ->pluck('id');
    }

    /**
     * Sub-kategori SKHPP per jenis, mis. ['D' => [{id, name}, ...], 'P' => [...]].
     * Sumbernya sama dengan sub-kategori Agenda Surat (tabel sub_categories).
     */
    public static function subCategories(): array
    {
        $out = ['D' => [], 'P' => []];
        foreach (['D', 'P'] as $jenis) {
            $ids = self::categoryIds($jenis);
            if ($ids->isNotEmpty()) {
                $out[$jenis] = \App\Models\SubCategory::whereIn('category_id', $ids)
                    ->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()->all();
            }
        }
        return $out;
    }

    /** Apakah sub-kategori milik kategori SKHPP jenis tersebut. */
    public static function subCategoryBelongs(?int $subCategoryId, string $jenis): bool
    {
        if (!$subCategoryId) {
            return true;
        }
        return \App\Models\SubCategory::where('id', $subCategoryId)
            ->whereIn('category_id', self::categoryIds($jenis))->exists();
    }

    /**
     * Tebak sub-kategori untuk SKHPP dari menu SINDEN: pengajuan pernikahan => "Nikah",
     * selain itu nama sub-kategori yang muncul di teks peruntukan (mis. "Dik" pada "Pendidikan").
     */
    public static function guessSubCategoryId(Skhpp $skhpp): ?int
    {
        $subs = self::subCategories()[self::jenisFor($skhpp->kategori_personel)] ?? [];
        if (!$subs) {
            return null;
        }

        if ($skhpp->is_pernikahan) {
            foreach ($subs as $s) {
                if (str_contains(strtolower($s['name']), 'nikah')) {
                    return $s['id'];
                }
            }
        }

        $text = strtolower((string) $skhpp->peruntukan);
        // Nama terpanjang dulu agar "Dik Lanjut" menang atas "Dik".
        usort($subs, fn ($a, $b) => strlen($b['name']) <=> strlen($a['name']));
        foreach ($subs as $s) {
            $name = strtolower(trim($s['name']));
            if ($name !== '' && strlen($name) >= 3 && str_contains($text, $name)) {
                return $s['id'];
            }
        }

        return null;
    }

    /** Nomor urut terbesar yang sudah terpakai untuk jenis & tahun tersebut. */
    public static function lastSequence(string $jenis, int $year): int
    {
        $fromArchive = (int) (SkhppArchive::where('jenis', $jenis)->where('tahun', $year)->max('nomor_urut') ?? 0);

        $skhppQuery = Skhpp::where('tahun', $year);
        $jenis === 'P'
            ? $skhppQuery->where('kategori_personel', 'perusahaan')
            : $skhppQuery->where(fn ($q) => $q->whereNull('kategori_personel')->orWhere('kategori_personel', '!=', 'perusahaan'));
        $fromSkhpp = (int) ($skhppQuery->max('nomor_urut') ?? 0);

        // Nomor SKHPP lama yang pernah di-booking lewat Agenda Surat (sebelum dipisah).
        $catIds = self::categoryIds($jenis);
        $fromAgenda = 0;
        if ($catIds->isNotEmpty()) {
            $fromAgenda = (int) LetterLog::whereIn('category_id', $catIds)
                ->whereYear('date', $year)
                ->pluck('sequence')
                ->filter(fn ($s) => is_numeric($s))
                ->map(fn ($s) => (int) $s)
                ->max();
        }

        return max($fromArchive, $fromSkhpp, $fromAgenda);
    }

    public static function nextSequence(string $jenis, int $year): int
    {
        $next = self::lastSequence($jenis, $year) + 1;

        // Hormati nomor awal yang pernah diatur di Kategori Surat (SKHPP-D / SKHPP-P).
        $start = (int) (Category::where('code', 'SKHPP-' . $jenis)->value('start_number') ?? 0);

        return max($next, $start, 1);
    }

    /** Apakah nomor urut sudah dipakai (oleh SKHPP SINDEN maupun manual). */
    public static function isTaken(string $jenis, int $year, int $sequence, ?int $ignoreArchiveId = null, ?int $ignoreSkhppId = null, ?string $suffix = null): bool
    {
        $sameSuffix = fn ($q) => $suffix === null
            ? $q->where(fn ($w) => $w->whereNull('nomor_sisipan')->orWhere('nomor_sisipan', ''))
            : $q->where('nomor_sisipan', $suffix);

        $archive = SkhppArchive::where('jenis', $jenis)->where('tahun', $year)->where('nomor_urut', $sequence)
            ->where($sameSuffix)
            ->where('status', '!=', SkhppArchive::STATUS_DICABUT)
            ->when($ignoreArchiveId, fn ($q) => $q->where('id', '!=', $ignoreArchiveId))
            ->when($ignoreSkhppId, fn ($q) => $q->where(fn ($w) => $w->whereNull('skhpp_id')->orWhere('skhpp_id', '!=', $ignoreSkhppId)))
            ->exists();
        if ($archive) {
            return true;
        }

        $skhpp = Skhpp::where('tahun', $year)->where('nomor_urut', $sequence)
            ->where($sameSuffix)
            ->when($ignoreSkhppId, fn ($q) => $q->where('id', '!=', $ignoreSkhppId));
        $jenis === 'P'
            ? $skhpp->where('kategori_personel', 'perusahaan')
            : $skhpp->where(fn ($q) => $q->whereNull('kategori_personel')->orWhere('kategori_personel', '!=', 'perusahaan'));

        return $skhpp->exists();
    }

    /**
     * Jalankan $callback secara eksklusif per jenis SKHPP agar dua petugas yang
     * meminta nomor bersamaan tidak mendapat nomor yang sama.
     */
    public static function withLock(string $jenis, callable $callback)
    {
        $run = fn () => DB::transaction(fn () => $callback());

        try {
            return Cache::lock('skhpp-number-' . $jenis, 15)->block(10, $run);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            throw $e;
        } catch (\BadMethodCallException $e) {
            // Driver cache tanpa dukungan lock: tetap jalan dalam transaksi.
            return $run();
        }
    }
}
