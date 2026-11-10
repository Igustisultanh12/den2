<?php

/*
|--------------------------------------------------------------------------
| Helper global API mobile (/api/mobile/*)
|--------------------------------------------------------------------------
|
| Dipindahkan dari routes/mobile_api.php. File route TIDAK dimuat ulang saat
| `php artisan route:cache` / `optimize` aktif, sehingga fungsi yang didefinisikan
| di sana hilang dan setiap endpoint yang memanggilnya gagal 500
| ("Call to undefined function mobile_payload()"). File ini dimuat dari
| bootstrap/app.php agar selalu tersedia.
|
*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

if (!function_exists('mobile_payload')) {
    /** Baca body JSON atau form. */
    function mobile_payload(Request $request): array
    {
        return $request->json()->all() ?: $request->all();
    }
}

if (!function_exists('mobile_error')) {
    /** Respons error standar tanpa membocorkan detail internal server. */
    function mobile_error(\Throwable $e, string $message = 'Terjadi kendala pada server.', int $status = 500)
    {
        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan.'], 404);
        }
        Log::error('[MOBILE_API] ' . $e->getMessage(), ['trace' => substr($e->getTraceAsString(), 0, 2000)]);
        return response()->json(['status' => 'error', 'message' => $message], $status);
    }
}

if (!function_exists('mobile_validation_error')) {
    function mobile_validation_error(\Illuminate\Contracts\Validation\Validator $v)
    {
        return response()->json([
            'status' => 'error',
            'message' => $v->errors()->first(),
            'errors' => $v->errors(),
        ], 422);
    }
}

if (!function_exists('mobile_recalculate_balances')) {
    /** Rekalkulasi saldo berjalan kronologis (logika lama dipertahankan). */
    function mobile_recalculate_balances(string $modelClass): void
    {
        $running = 0.0;
        foreach ($modelClass::orderBy('date', 'asc')->orderBy('id', 'asc')->get() as $item) {
            $running += ((float) $item->debit - (float) $item->credit);
            if ((float) $item->balance !== $running) {
                $item->balance = $running;
                $item->save();
            }
        }
    }
}

if (!function_exists('mobile_debit_credit')) {
    /** @return array{0: float, 1: float} */
    function mobile_debit_credit(array $data, string $defaultType, float $oldDebit = 0.0, float $oldCredit = 0.0, bool $isUpdate = false): array
    {
        if (isset($data['debit']) || isset($data['credit'])) {
            return [max(0.0, (float) ($data['debit'] ?? 0)), max(0.0, (float) ($data['credit'] ?? 0))];
        }
        if (isset($data['amount'])) {
            $type = strtolower($data['type'] ?? $defaultType);
            $amount = max(0.0, (float) $data['amount']);
            return in_array($type, ['masuk', 'debit'], true) ? [$amount, 0.0] : [0.0, $amount];
        }
        return $isUpdate ? [$oldDebit, $oldCredit] : [0.0, 0.0];
    }
}

if (!function_exists('mobile_delegate')) {
    /**
     * Jalankan aksi controller web yang sudah ada (agar business logic, nomor
     * surat, TTE, notifikasi WA, dan audit log tidak diduplikasi), lalu ubah
     * redirect + flash message-nya menjadi respons JSON untuk aplikasi mobile.
     */
    function mobile_delegate(callable $action, string $defaultSuccess = 'Berhasil diproses.')
    {
        $keys = ['success', 'message', 'warning', 'error'];
        session()->forget($keys);
        try {
            $response = $action();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => collect($e->errors())->flatten()->first() ?? 'Data tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage() ?: 'Akses ditolak.'], $e->getStatusCode());
        } catch (\Throwable $e) {
            return mobile_error($e, 'Gagal memproses permintaan.');
        }

        $flash = collect($keys)->mapWithKeys(fn ($k) => [$k => session($k)])->all();
        session()->forget($keys);

        if (!empty($flash['error'])) {
            return response()->json(['status' => 'error', 'message' => (string) $flash['error']], 422);
        }
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }
        $message = $flash['success'] ?? $flash['message'] ?? $flash['warning'] ?? $defaultSuccess;
        return response()->json(['status' => 'success', 'message' => (string) $message]);
    }
}
