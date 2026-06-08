<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetSessionConfig
{
    public function handle(Request $request, Closure $next)
    {
        if (
            $request->getHost() === 'admin.rewardsapp.in' &&
            ! $request->is('api/*') &&
            ! $request->is('sanctum/csrf-cookie')
        ) {
            config([
                'session.cookie' => 'filament_session',
                'session.domain' => 'admin.rewardsapp.in',
            ]);
        }

        return $next($request);
    }
}
