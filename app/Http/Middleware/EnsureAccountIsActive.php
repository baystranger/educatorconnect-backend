<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended or deactivated account loses access immediately, even if it still holds a
 * browser session or an API token issued before the change.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($user->status, ['suspended', 'deactivated'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This account is not active.',
                'errors' => ['account' => ['This account is '.$user->status.'.']],
            ], 403);
        }

        return $next($request);
    }
}
