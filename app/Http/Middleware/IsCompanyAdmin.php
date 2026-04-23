<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsCompanyAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): 
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $allowedRoles = ['business_head', 'sub_admin'];
        
        if (!in_array($user->user_type, $allowedRoles)) {
            return response()->json([
                'message' => 'Access Denied: This portal is strictly for Company Administrators.'
            ], 403);
        }

        if ($user->company_id && !$user->company->is_active) {
             return response()->json([
                'message' => 'Account Suspended: Your company account is currently inactive.'
            ], 403);
        }

        return $next($request);
    }
}