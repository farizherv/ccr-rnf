<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyCcrAgentToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('X-CCR-AGENT-TOKEN');

        if (!$token || !hash_equals((string) env('CCR_AGENT_TOKEN'), (string) $token)) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        return $next($request);
    }
}
