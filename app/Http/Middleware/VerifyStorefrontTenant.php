<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyStorefrontTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user      = $request->user();
        $routeSlug = $request->route('slug');

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->company->alias !== $routeSlug) {
            return response()->json([
                'message' => 'Unauthorized: This storefront does not belong to your company.',
            ], 403);
        }

        return $next($request);
    }
}
