<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        // ✅ normalisasi role allowed (support role:admin,operator)
        $allowed = [];
        foreach ($roles as $r) {
            foreach (explode(',', $r) as $part) {
                $part = strtolower(trim($part));
                if ($part !== '') $allowed[] = $part;
            }
        }

        if (!in_array($userRole, $allowed, true)) {
            abort(403, "FORBIDDEN: ROLE NOT ALLOWED | userRole={$userRole} | allowed=" . implode(',', $allowed) . " | userId={$user->id}");
        }

        return $next($request);
    }
}
