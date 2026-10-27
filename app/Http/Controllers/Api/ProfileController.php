<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\PasswordChangedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfileController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();

        $user->load(['company.wallet', 'wallet', 'addresses']);

        if ($user->user_type === 'sub_admin') {
            $user->load('managedVerticals');
        }

        if ($user->user_type === 'rewardee') {
            $user->load('rewardeeProfile.vertical');
        }

        return response()->json([
            'message' => 'Profile retrieved successfully',
            'data'    => new UserResource($user),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name'              => ['required', 'string', 'max:255'],
            'last_name'               => ['required', 'string', 'max:255'],
            'email'                   => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'mobile'                  => ['nullable', 'string', 'max:20'],
            'designation'             => ['nullable', 'string', 'max:255'],
            'department'              => ['nullable', 'string', 'max:255'],

            // Optional for the customer-admin profile form, but kept here for
            // storefront users that still edit personal/address information.
            'gender'                  => ['sometimes', 'nullable', 'string', 'max:50'],
            'dob'                     => ['sometimes', 'nullable', 'date'],
            'address_line_1'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'                    => ['sometimes', 'nullable', 'string', 'max:100'],
            'state'                   => ['sometimes', 'nullable', 'string', 'max:100'],
            'pincode'                 => ['sometimes', 'nullable', 'string', 'max:20'],
            'country'                 => ['sometimes', 'nullable', 'string', 'max:100'],
            'shipping_address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shipping_address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shipping_city'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'shipping_state'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'shipping_pincode'        => ['sometimes', 'nullable', 'string', 'max:20'],
            'shipping_country'        => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($validated, $user) {
            $userUpdates = [
                'first_name'  => $validated['first_name'],
                'last_name'   => $validated['last_name'],
                'name'        => trim($validated['first_name'] . ' ' . $validated['last_name']),
                'email'       => $validated['email'],
                'mobile'      => $validated['mobile'] ?? null,
                'designation' => $validated['designation'] ?? null,
                'department'  => $validated['department'] ?? null,
            ];

            foreach (['gender', 'dob'] as $optionalUserField) {
                if (array_key_exists($optionalUserField, $validated)) {
                    $userUpdates[$optionalUserField] = $validated[$optionalUserField];
                }
            }

            $user->update($userUpdates);

            if (! empty($validated['address_line_1'])) {
                $user->addresses()->updateOrCreate(
                    ['type' => 'home'],
                    [
                        'contact_name'   => trim($validated['first_name'] . ' ' . $validated['last_name']),
                        'contact_mobile' => $validated['mobile'] ?? 'N/A',
                        'address_line_1' => $validated['address_line_1'],
                        'address_line_2' => $validated['address_line_2'] ?? null,
                        'city'           => $validated['city'] ?? null,
                        'state'          => $validated['state'] ?? null,
                        'pincode'        => $validated['pincode'] ?? null,
                        'country'        => $validated['country'] ?? 'India',
                        'is_default'     => true,
                    ],
                );
            }

            if (! empty($validated['shipping_address_line_1'])) {
                $user->addresses()->updateOrCreate(
                    ['type' => 'shipping'],
                    [
                        'contact_name'   => trim($validated['first_name'] . ' ' . $validated['last_name']),
                        'contact_mobile' => $validated['mobile'] ?? 'N/A',
                        'address_line_1' => $validated['shipping_address_line_1'],
                        'address_line_2' => $validated['shipping_address_line_2'] ?? null,
                        'city'           => $validated['shipping_city'] ?? null,
                        'state'          => $validated['shipping_state'] ?? null,
                        'pincode'        => $validated['shipping_pincode'] ?? null,
                        'country'        => $validated['shipping_country'] ?? 'India',
                        'is_default'     => false,
                    ],
                );
            }
        });

        $user->load(['company.wallet', 'wallet', 'addresses']);

        if ($user->user_type === 'sub_admin') {
            $user->load('managedVerticals');
        }

        if ($user->user_type === 'rewardee') {
            $user->load('rewardeeProfile.vertical');
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data'    => new UserResource($user),
        ]);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match our records.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        try {
            Mail::to($user->email)->send(new PasswordChangedMail($user));
        } catch (Throwable $exception) {
            // The password change has succeeded; don't report it as failed just
            // because the notification service was temporarily unavailable.
            Log::error('Failed to send password-change notification email.', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}
