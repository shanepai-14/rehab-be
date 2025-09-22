<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Your account needs to be verified before accessing this resource.',
                'requires_verification' => true
            ], 403);
        }

        return $next($request);
    }
}