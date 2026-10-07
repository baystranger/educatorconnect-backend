<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks trust-sensitive actions (applying, publishing) until the account email is verified.
 */
class EnsureEmailIsVerifiedForApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before doing this.',
                'errors' => ['email' => ['Email address is not verified.']],
            ], 403);
        }

        return $next($request);
    }
}
