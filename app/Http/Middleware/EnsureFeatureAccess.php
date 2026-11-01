<?php

namespace App\Http\Middleware;

use App\Support\FeatureAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemakaian: ->middleware('feature:skhpp') atau 'feature:skhpp,signature'
 * (lolos bila salah satu fitur aktif). Admin & Super Admin selalu lolos.
 */
class EnsureFeatureAccess
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $user = $request->user();

        foreach ($features as $feature) {
            if (FeatureAccess::allows($user, $feature)) {
                return $next($request);
            }
        }

        $label = FeatureAccess::FEATURES[$features[0] ?? '']['label'] ?? 'ini';
        $message = "Fitur {$label} tidak diaktifkan untuk akun Anda. Hubungi Admin untuk meminta akses.";

        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            return response()->json(['status' => 'error', 'message' => $message], 403);
        }

        abort(403, $message);
    }
}
