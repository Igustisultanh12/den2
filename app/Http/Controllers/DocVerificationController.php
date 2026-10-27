<?php

namespace App\Http\Controllers;

use App\Models\SignatureRequest;
use App\Models\Skhpp;
use App\Models\SpJaga;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DocVerificationController extends Controller
{
    private function getRomanMonth($month)
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        return $map[(int)$month] ?? 'I';
    }

    private function formatUserJabatan($user)
    {
        if (!$user) return 'PERSONEL SATUAN';
        
        if (!empty($user->jabatan) && !in_array(strtolower(trim($user->jabatan)), ['personel', 'personel satuan'])) {
            return $user->jabatan;
        }

        $role = strtolower(trim($user->role ?? ''));
        $roleMap = [
            'admin'         => 'ADMINISTRATOR SISTEM',
            'komandan'      => 'KOMANDAN',
            'wadan'         => 'WAKIL KOMANDAN',
            'pasops'        => 'PASOPS',
            'pasiops'       => 'PASIOPS',
            'pasimin'       => 'PASIMIN',
            'pasintel'      => 'PASINTEL',
            'pasilog'       => 'PASILOG',
            'dantim'        => 'DANTIM',
            'danunit'       => 'DANUNIT',
            'danunit1'      => 'DAN UNIT I / LID',
            'danunit2'      => 'DAN UNIT II / PAMGAL',
            'danunitteknis' => 'DAN UNIT TEKNIS',
            'kaurmintel'    => 'KAUR MINTEL',
            'paurset'       => 'PAUR SET',
            'staf'          => 'STAF ADMINISTRASI',
            'personel'      => 'PERSONEL SATUAN',
            'anggotasintel' => 'ANGGOTA SINTEL',
            'bintara'       => 'BINTARA INTEL',
            'tamtama'       => 'TAMTAMA INTEL',
            'pns'           => 'PNS INTEL',
        ];

        return $roleMap[$role] ?? (!empty($role) ? strtoupper($role) : 'PERSONEL SATUAN');
    }

    /**
     * Halaman Publik Verifikasi TTE Berkas Dinas Lainnya
     */
    public function verify($code)
    {
        $code = trim($code);
        $settings = Setting::pluck('value', 'key')->all();

        // 1. Jika kode milik SKHPP, alihkan ke Verifikasi SKHPP
        if (str_starts_with(strtoupper($code), 'SKHPP-') || Skhpp::where('verification_code', $code)->orWhere('nomor_skhpp', $code)->exists()) {
            return redirect()->route('skhpp.verify', $code);
        }

        // 2. Cari Berkas di Tabel SpJaga (Surat Perintah Jaga Siaga Sintel)
        $spJaga = SpJaga::where('verification_code', $code)
            ->orWhere('verification_code', 'like', "%{$code}%")
            ->orWhere('nomor_sprin', $code)
            ->first();

        if ($spJaga) {
            // Ambil Data Pejabat Pengaju / Pimpinan Jaga (Dan Unit 1 Lid - Kapten Laut (P) Indra Gunawan)
            $danunitUser = User::where('role', 'danunit1')->first()
                        ?? User::where('name', 'like', '%Indra Gunawan%')->first()
                        ?? User::where('role', 'danunit')->first();

            $danunitPangkat = $danunitUser?->pangkat ?? 'Kapten Laut (P)';
            $danunitNama = $danunitUser?->name ?? 'Indra Gunawan T.Z';
            $danunitNrp = $danunitUser?->nrp ? 'NRP ' . $danunitUser->nrp : 'NRP 19739/P';
            $danunitJabatan = 'Dan Unit 1 Lid / Denintel Koarmada 2';

            // Ambil Data Pejabat Penandatangan Pasops / Pasiops
            $pasopsUser = User::where('role', 'pasops')->first()
                       ?? User::where('role', 'like', '%pasops%')->first()
                       ?? User::where('role', 'like', '%pasiops%')->first()
                       ?? User::where('name', 'like', '%Roni%')->first();

            $pName = $pasopsUser?->name ?? ($spJaga->penandatangan_nama ?: 'Roni Sumantri');
            $pRank = $pasopsUser?->pangkat ?? 'Mayor Laut (P)';
            $pNrp = ($pasopsUser && $pasopsUser->nrp) ? 'NRP ' . $pasopsUser->nrp : ($spJaga->penandatangan_pangkat_nrp ?: 'NRP 17456/P');
            $pRankNrp = str_contains($pNrp, $pRank) ? $pNrp : "{$pRank} {$pNrp}";

            $bulanIndoList = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];
            $blnNamaBaku = $bulanIndoList[(int)$spJaga->bulan] ?? 'Januari';
            $namaBulanTahun = strtoupper("{$blnNamaBaku} {$spJaga->tahun}");
            $docData = (object) [
                'id' => $spJaga->id,
                'verification_code' => $spJaga->verification_code,
                'document_title' => 'SURAT PERINTAH DINAS JAGA SIAGA SINTEL',
                'letter_number' => $spJaga->nomor_sprin,
                'subject' => "Dinas Jaga Siaga Sintel Bulan {$namaBulanTahun}",
                'peruntukan' => "Pelaksanaan Tugas Jaga Siaga Sintel Denintel Koarmada 2 Periode Bulan {$namaBulanTahun}",
                'nama' => "$danunitNama dkk (" . ($spJaga->total_personel_count ?: '27') . " Personel)",
                'pangkat_korps_nrp' => "$danunitPangkat / $danunitNrp",
                'jabatan_pekerjaan' => $danunitJabatan,
                'tanggal_dokumen' => $spJaga->approved_at ?: $spJaga->tanggal_surat,
                'status' => ($spJaga->status === 'published') ? 'approved' : 'pending',
                'is_valid' => ($spJaga->status === 'published'),
                'signer_name' => "a.n. KOMANDAN DETASEMEN INTELIJEN KOARMADA 2",
                'signer_title' => "Pasiops - {$pRankNrp} {$pName}",
            ];

            return Inertia::render('Verify/DocVerify', [
                'doc' => $docData,
                'is_valid' => ($spJaga->status === 'published'),
                'verify_code' => $code,
                'settings' => $settings,
            ]);
        }

        // 3. Cari Berkas di Tabel SignatureRequest (Naskah Dinas / Dokumen Lainnya)
        $sigReq = SignatureRequest::with('user')
            ->where('verification_code', $code)
            ->orWhere('verification_code', 'like', "%{$code}%")
            ->orWhere('letter_number', $code)
            ->first();

        // 4. Logika Penentuan Pejabat Penandatangan (Komandan Langsung vs a.n. Komandan)
        $komandan = User::where('role', 'komandan')->first() 
                 ?? User::where('name', 'like', '%Hari Bagio%')->first();

        $approverUser = null;
        if ($sigReq && !empty($sigReq->approved_by)) {
            $approverUser = User::find($sigReq->approved_by);
        }

        $isKomandanLangsung = false;
        if ($approverUser) {
            $isKomandanLangsung = ($approverUser->role === 'komandan' || str_contains(strtolower($approverUser->name), 'hari bagio'));
        } elseif ($sigReq) {
            $isKomandanLangsung = ($sigReq->user?->role === 'komandan');
        }

        if ($isKomandanLangsung) {
            $cName = $komandan?->name ?? 'Hari Bagio Wijayanto, M.Tr.Opsla.';
            $cRank = $komandan?->pangkat ?? 'Kolonel Laut (E)';
            $cNrp = ($komandan?->nrp && $komandan->nrp !== '00000000000000') ? $komandan->nrp : '16085/P';

            $signerName = strtoupper($cName);
            $signerTitle = "Komandan Detasemen Intelijen Koarmada 2 - {$cRank} NRP {$cNrp}";
        } else {
            $aUser = $approverUser ?: ($sigReq?->user ?: auth()->user());
            $aName = $aUser?->name ?? 'Administrator SINDEN';
            $aRank = $aUser?->pangkat ?? 'Letnan Dua Laut (KC)';
            $aNrp = ($aUser?->nrp && $aUser->nrp !== '00000000000000') ? $aUser->nrp : '12000018012200216';

            $signerName = "a.n. KOMANDAN DETASEMEN INTELIJEN KOARMADA 2";
            $signerTitle = "{$aRank} {$aName} NRP {$aNrp}";
        }

        if ($sigReq) {
            $applicantUser = null;
            if (!empty($sigReq->person_name)) {
                $applicantUser = User::where('name', trim($sigReq->person_name))->first();
            }
            if (!$applicantUser && !empty($sigReq->pangkat_nrp)) {
                if (preg_match('/NRP\.?\s*([A-Za-z0-9\/]+)/i', $sigReq->pangkat_nrp, $matches)) {
                    $extractedNrp = trim($matches[1]);
                    $applicantUser = User::where('nrp', $extractedNrp)->first();
                }
            }
            if (!$applicantUser) {
                $applicantUser = $sigReq->user ?: ($sigReq->user_id ? User::find($sigReq->user_id) : null);
            }

            $applicantName = $sigReq->person_name ?: ($applicantUser?->name ?? 'Personel Denintel Koarmada 2');
            $applicantRank = $applicantUser?->pangkat ?: 'Prajurit TNI AL';
            $applicantNrp = ($applicantUser?->nrp && $applicantUser->nrp !== '00000000000000') ? $applicantUser->nrp : ($applicantUser?->username ?? '-');
            
            $applicantIdentity = $sigReq->pangkat_nrp ?: "{$applicantRank} / NRP {$applicantNrp}";
            
            $userJabatanName = $this->formatUserJabatan($applicantUser);
            $sigReqJabatan = trim($sigReq->jabatan ?? '');
            
            $isGenericJabatan = empty($sigReqJabatan) 
                             || str_contains(strtolower($sigReqJabatan), 'personel') 
                             || str_contains(strtolower($sigReqJabatan), 'pendaftaran otoritas');

            if (!$isGenericJabatan) {
                $applicantPosition = $sigReqJabatan;
            } else {
                $applicantPosition = str_contains(strtolower($userJabatanName), 'denintel') 
                                   ? $userJabatanName 
                                   : ($userJabatanName . ' / Denintel Koarmada 2');
            }

            $signedDate = $sigReq->approved_at ?: $sigReq->updated_at;
            $isValid = in_array(strtolower($sigReq->status), ['approved', 'signed', 'selesai']);

            $uniqueDocCode = $sigReq->verification_code;
            if (empty($uniqueDocCode) || is_numeric($uniqueDocCode) || strlen($uniqueDocCode) > 25) {
                $uniqueDocCode = 'TTE-DOC-' . date('Ymd', strtotime($sigReq->created_at ?? now())) . '-' . strtoupper(substr(md5($sigReq->id . ($sigReq->created_at ?? now())), 0, 6));
            }

            $docData = (object) [
                'id' => $sigReq->id,
                'verification_code' => $uniqueDocCode,
                'document_title' => $sigReq->document_title ?: 'NASKAH DINAS / DOKUMEN RESMI KEDINASAN',
                'letter_number' => $sigReq->letter_number ?: ("B/" . $sigReq->id . "/VIII/" . date('Y') . "/Denintel"),
                'subject' => $sigReq->subject ?: 'Surat Dinas Resmi Kedinasan',
                'peruntukan' => $sigReq->peruntukan ?: $sigReq->subject,
                'nama' => $applicantName,
                'pangkat_korps_nrp' => $applicantIdentity,
                'jabatan_pekerjaan' => $applicantPosition,
                'tanggal_dokumen' => $signedDate,
                'status' => $sigReq->status,
                'is_valid' => $isValid,
                'signer_name' => $signerName,
                'signer_title' => $signerTitle,
            ];

            return Inertia::render('Verify/DocVerify', [
                'doc' => $docData,
                'is_valid' => $isValid,
                'verify_code' => $code,
                'settings' => $settings
            ]);
        }

        return Inertia::render('Verify/DocVerify', [
            'doc' => null,
            'is_valid' => false,
            'verify_code' => $code,
            'settings' => $settings
        ]);
    }
}