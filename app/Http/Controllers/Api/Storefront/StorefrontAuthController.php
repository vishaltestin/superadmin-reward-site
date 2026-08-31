<?php
namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class StorefrontAuthController extends Controller
{
    public function login(Request $request, $slug)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $company = Company::where('alias', $slug)->where('is_active', true)->first();
        if (! $company) {
            return response()->json(['message' => 'Storefront not found.'], 404);
        }

        $throttleKey = 'storefront:' . Str::transliterate(Str::lower($request->email) . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey);
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($user->user_type !== 'rewardee') {
            return response()->json(['message' => 'Admins cannot shop on the storefront.'], 403);
        }
        if ($user->company_id !== $company->id) {
            return response()->json(['message' => 'Access Denied: Tenant mismatch.'], 403);
        }
        if (! $user->is_active) {
            return response()->json(['message' => 'Account Suspended.'], 403);
        }

        RateLimiter::clear($throttleKey);

        $user->load(['company', 'rewardeeProfile', 'wallet']);

        $token = $user->createToken('storefront_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => new UserResource($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
