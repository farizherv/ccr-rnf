<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Route;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403, 'FORBIDDEN');
        }

        // ✅ normalisasi role user (anti DIRECTOR vs director)
        $userRole = strtolower(trim((string) $user->role));

        // ✅ normalisasi role allowed (support role:admin,director)
        $allowed = [];
        foreach ($roles as $r) {
            foreach (explode(',', $r) as $part) {
                $part = strtolower(trim($part));
                if ($part !== '') $allowed[] = $part;
            }
        }

        if (!in_array($userRole, $allowed, true)) {

            // ======================================================
            // ✅ AJAX/JSON request -> balikin 403 json
            // ======================================================
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message'  => 'FORBIDDEN',
                    'userRole' => $userRole,
                    'allowed'  => $allowed,
                    'userId'   => $user->id,
                ], 403);
            }

            // ======================================================
            // ✅ Normal page -> redirect + popup locked
            // ======================================================
            // kalau ada previous url, pakai itu. kalau gak ada, pakai fallback aman.
            $fallback = '/'; // aman pasti ada

            // kalau kamu punya route menu edit CCR yang benar, taruh di sini:
            // contoh: ccr.edit.menu atau ccr.edit.index atau trash.menu, dll.
            if (Route::has('ccr.edit.menu')) {
                $fallback = route('ccr.edit.menu');
            } elseif (Route::has('ccr.edit.index')) {
                $fallback = route('ccr.edit.index');
            } elseif (Route::has('ccr.edit')) {
                $fallback = route('ccr.edit');
            }

            $target = url()->previous();
            if (!$target || $target === $request->fullUrl()) {
                $target = $fallback;
            }

            return redirect()->to($target)->with('locked', 'you cannot access this');
        }

        return $next($request);
    }
}
