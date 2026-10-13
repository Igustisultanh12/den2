<?php

namespace App\Support;

use App\Models\SpJaga;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mengubah data Surat Perintah (SP) Jaga — yang di web disimpan sebagai tabel
 * lampiran (perwira tgl_1..tgl_7 & daftar divisi anggota) — menjadi jadwal
 * jaga per tanggal yang siap ditampilkan di aplikasi mobile.
 *
 * Tidak ada data yang dikarang: setiap entri berasal langsung dari baris
 * sp_jaga_perwiras / sp_jaga_anggotas.
 */
class DutyRoster
{
    private const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public static function visibleQuery(User $user): Builder
    {
        $query = SpJaga::query();
        if (!MobileAccess::can($user, 'duty_roster.view_unpublished')) {
            $query->where('status', 'published');
        }
        return $query;
    }

    public static function canSee(User $user, SpJaga $sp): bool
    {
        return $sp->status === 'published' || MobileAccess::can($user, 'duty_roster.view_unpublished');
    }

    /** Bulan & tahun efektif SP (mengikuti TMT mulai seperti PDF resmi). */
    public static function period(SpJaga $sp): array
    {
        $month = (int) $sp->bulan;
        $year = (int) $sp->tahun;
        $raw = $sp->getAttributes()['tmt_mulai'] ?? null;
        if (!empty($raw)) {
            $c = Carbon::parse($raw);
            $month = (int) $c->format('n');
            $year = (int) $c->format('Y');
        }
        return [$month, $year];
    }

    public static function summary(SpJaga $sp): array
    {
        [$month, $year] = self::period($sp);

        return [
            'id' => $sp->id,
            'nomor_sprin' => $sp->nomor_sprin,
            'bulan' => $month,
            'tahun' => $year,
            'periode_label' => (self::BULAN[$month] ?? '') . ' ' . $year,
            'status' => $sp->status,
            'ttd_type' => $sp->ttd_type,
            'tmt_mulai' => $sp->tmt_mulai_formatted,
            'tmt_selesai' => $sp->tmt_selesai_formatted,
            'tanggal_surat' => $sp->tanggal_surat_formatted,
            'total_personel' => (int) $sp->total_personel_count,
            'perwira_tertua' => $sp->perwira_tertua_nama,
            'penandatangan' => $sp->penandatangan_nama,
            'approved_at' => optional($sp->approved_at)->toIso8601String(),
        ];
    }

    private static function matchesUser(User $user, ?int $userId, ?string $nrp, ?string $nama): bool
    {
        if ($userId && $userId === (int) $user->id) {
            return true;
        }
        if (!empty($user->nrp) && !empty($nrp) && trim($nrp) === trim((string) $user->nrp)) {
            return true;
        }
        // Sama dengan logika web (SpJagaController@index): nama mengandung nama pengguna.
        return !empty($nama) && !empty($user->name)
            && str_contains(strtolower($nama), strtolower($user->name));
    }

    /** @return list<int> */
    private static function dayNumbers(array $values, int $daysInMonth): array
    {
        $days = [];
        foreach ($values as $v) {
            if (is_int($v) || (is_string($v) && preg_match('/^\s*\d{1,2}\s*$/', $v))) {
                $d = (int) $v;
                if ($d >= 1 && $d <= $daysInMonth) {
                    $days[] = $d;
                }
            } elseif (is_string($v) && preg_match('/^\d{4}-\d{2}-(\d{2})/', $v, $m)) {
                $d = (int) $m[1];
                if ($d >= 1 && $d <= $daysInMonth) {
                    $days[] = $d;
                }
            }
        }
        $days = array_values(array_unique($days));
        sort($days);
        return $days;
    }

    /**
     * Bangun jadwal per tanggal untuk satu SP Jaga.
     *
     * @return array{days: list<array>, my_dates: list<string>, my_roles: list<string>}
     */
    public static function build(SpJaga $sp, User $user): array
    {
        $sp->loadMissing(['perwiras', 'anggotas']);
        [$month, $year] = self::period($sp);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        /** @var array<int, list<array>> $byDay */
        $byDay = [];
        $myDays = [];
        $myRoles = [];

        foreach ($sp->perwiras as $p) {
            $values = [];
            for ($i = 1; $i <= 7; $i++) {
                $values[] = $p->{"tgl_{$i}"};
            }
            if (is_array($p->tgl_list)) {
                $values = array_merge($values, $p->tgl_list);
            }
            $isMe = self::matchesUser($user, $p->user_id, $p->nrp, $p->nama);
            foreach (self::dayNumbers($values, $daysInMonth) as $d) {
                $byDay[$d][] = [
                    'name' => $p->nama,
                    'pangkat_korps' => $p->pangkat_korps ?? '',
                    'nrp' => $p->nrp ?? '',
                    'role' => 'Perwira Siaga Sintel',
                    'role_code' => 'PERWIRA',
                    'divisi_no' => null,
                    'is_me' => $isMe,
                ];
                if ($isMe) {
                    $myDays[] = $d;
                    $myRoles['Perwira Siaga Sintel'] = true;
                }
            }
        }

        foreach ($sp->anggotas as $div) {
            $values = is_array($div->tanggal_array) ? $div->tanggal_array : [];
            $days = self::dayNumbers($values, $daysInMonth);
            if (empty($days) && !empty($div->tanggal_list_text)) {
                preg_match_all('/\b(\d{1,2})\b/', (string) $div->tanggal_list_text, $m);
                $days = self::dayNumbers($m[1] ?? [], $daysInMonth);
            }
            $items = is_array($div->anggota_items) ? $div->anggota_items : (json_decode((string) $div->anggota_items, true) ?: []);

            foreach ($items as $item) {
                $roleCode = strtoupper((string) ($item['role_jaga'] ?? 'ANGGOTA'));
                $roleLabel = $roleCode === 'BAGA' ? 'Bintara Jaga (BAGA)' : 'Anggota Siaga Sintel';
                $isMe = self::matchesUser(
                    $user,
                    isset($item['user_id']) && $item['user_id'] !== '' ? (int) $item['user_id'] : null,
                    $item['nrp_nip'] ?? null,
                    $item['nama'] ?? null
                );
                foreach ($days as $d) {
                    $byDay[$d][] = [
                        'name' => $item['nama'] ?? '-',
                        'pangkat_korps' => $item['pangkat_korps'] ?? '',
                        'nrp' => $item['nrp_nip'] ?? '',
                        'role' => $roleLabel,
                        'role_code' => $roleCode,
                        'divisi_no' => (int) $div->divisi_no,
                        'is_me' => $isMe,
                    ];
                    if ($isMe) {
                        $myDays[] = $d;
                        $myRoles[$roleLabel] = true;
                    }
                }
            }
        }

        ksort($byDay);
        $today = now()->toDateString();
        $days = [];
        foreach ($byDay as $d => $entries) {
            $date = Carbon::create($year, $month, $d);
            $days[] = [
                'date' => $date->toDateString(),
                'day_of_week' => $date->dayOfWeekIso, // 1 = Senin ... 7 = Minggu
                'is_today' => $date->toDateString() === $today,
                'has_me' => collect($entries)->contains('is_me', true),
                'entries' => $entries,
            ];
        }

        $myDays = array_values(array_unique($myDays));
        sort($myDays);

        return [
            'days' => $days,
            'my_dates' => array_map(fn ($d) => Carbon::create($year, $month, $d)->toDateString(), $myDays),
            'my_roles' => array_keys($myRoles),
        ];
    }

    /**
     * Pilih SP yang relevan: by id, by bulan/tahun, atau SP periode berjalan.
     */
    public static function resolve(User $user, ?int $spId, ?int $month, ?int $year): ?SpJaga
    {
        if ($spId) {
            $sp = SpJaga::find($spId);
            return ($sp && self::canSee($user, $sp)) ? $sp : null;
        }

        $candidates = self::visibleQuery($user)
            ->orderBy('tahun', 'desc')->orderBy('bulan', 'desc')->orderBy('id', 'desc')
            ->limit(60)->get();

        $targetMonth = $month ?: (int) now()->format('n');
        $targetYear = $year ?: (int) now()->format('Y');

        // Prioritaskan SP yang sudah diterbitkan untuk periode yang diminta.
        $sorted = $candidates->sortBy(fn (SpJaga $s) => $s->status === 'published' ? 0 : 1);
        $match = $sorted->first(function (SpJaga $s) use ($targetMonth, $targetYear) {
            [$m, $y] = self::period($s);
            return $m === $targetMonth && $y === $targetYear;
        });

        if ($match || $month || $year) {
            return $match;
        }

        return $candidates->firstWhere('status', 'published');
    }
}
