<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->email) . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json(['message' => "Too many attempts. Try again in {$seconds}s."], 429);
        }

        if (Auth::attempt($credentials)) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            $user = Auth::user()->load(['company.wallet', 'wallet']);

            if ($user->user_type === 'sub_admin') {
                $user->load('managedVerticals');
            }

            if (! in_array($user->user_type, ['business_head', 'sub_admin'])) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                return response()->json(['message' => 'Access Denied: This portal is for Company Administrators only.'], 403);
            }

            if (! $user->is_active || ($user->company_id && ! $user->company->is_active)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                return response()->json(['message' => 'Account Suspended.'], 403);
            }

            return response()->json([
                'message' => 'Admin Login successful',
                'user'    => new UserResource($user),
            ]);
        }

        RateLimiter::hit($throttleKey);
        return response()->json(['message' => 'Invalid credentials.'], 401);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Logged out successfully']);
    }
}
