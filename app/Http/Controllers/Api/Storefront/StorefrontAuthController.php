<?php
namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\StorefrontPasswordResetMail;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

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

    public function forgotPassword(Request $request, $slug)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $company = Company::where('alias', $slug)->where('is_active', true)->first();
        if (! $company) {
            return response()->json(['message' => 'Storefront not found.'], 404);
        }

        $throttleKey = 'storefront-forgot-password:' . Str::transliterate(
            Str::lower($validated['email']) . '|' . $company->id . '|' . $request->ip()
        );

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Too many reset attempts. Try again in {$seconds} seconds.",
            ], 429);
        }

        RateLimiter::hit($throttleKey, 300);

        $user = User::where('email', $validated['email'])
            ->where('company_id', $company->id)
            ->where('user_type', 'rewardee')
            ->where('is_active', true)
            ->first();

        if ($user) {
            $token   = Password::broker()->createToken($user);
            $expires = (int) config('auth.passwords.users.expire', 60);
            $url     = $this->passwordResetUrl($company, $user->email, $token);

            Mail::to($user->email)->send(
                new StorefrontPasswordResetMail($user, $company, $url, $expires)
            );
        }

        // Intentionally generic: do not disclose whether the email is registered,
        // active, or belongs to this tenant.
        return response()->json([
            'message' => 'If an active account exists for this email, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request, $slug)
    {
        $validated = $request->validate([
            'token'                 => 'required|string',
            'email'                 => 'required|email',
            'password'              => 'required|string|min:8|max:255|confirmed',
            'password_confirmation' => 'required|string|max:255',
        ]);

        $company = Company::where('alias', $slug)->where('is_active', true)->first();
        if (! $company) {
            return response()->json(['message' => 'Storefront not found.'], 404);
        }

        $user = User::where('email', $validated['email'])
            ->where('company_id', $company->id)
            ->where('user_type', 'rewardee')
            ->where('is_active', true)
            ->first();

        if (! $user) {
            return $this->invalidResetLinkResponse();
        }

        $status = Password::reset(
            [
                'email'                 => $validated['email'],
                'password'              => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
                'token'                 => $validated['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke every existing Sanctum token, including other devices.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->invalidResetLinkResponse();
        }

        return response()->json([
            'message' => 'Password reset successfully. Please log in with your new password.',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    private function passwordResetUrl(Company $company, string $email, string $token): string
    {
        $base = rtrim((string) config('app.storefront_url'), '/');
        $base = preg_replace('#/marketplace/?$#i', '', $base);

        if (! $base) {
            throw new RuntimeException('STOREFRONT_URL is not configured.');
        }

        return "{$base}/marketplace/{$company->alias}/reset-password"
        . '?token=' . rawurlencode($token)
        . '&email=' . rawurlencode($email);
    }

    private function invalidResetLinkResponse()
    {
        return response()->json([
            'message' => 'This password reset link is invalid or has expired. Please request a new one.',
        ], 400);
    }
}
