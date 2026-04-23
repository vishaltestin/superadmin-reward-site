<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     * Usage in routes: middleware('role:business_head,super_admin')
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->user_type, $roles)) {
            return response()->json([
                'message' => 'Forbidden: You do not have the required permissions to perform this action.'
            ], 403);
        }

        return $next($request);
    }
}