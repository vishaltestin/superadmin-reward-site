<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'first_name'              => 'required|string|max:255',
            'last_name'               => 'required|string|max:255',
            'gender'                  => 'required|string|max:50',
            'dob'                     => 'required|date',
            'mobile'                  => 'nullable|string|max:20',
            'email'                   => ['required', 'email', Rule::unique('users')->ignore($user->id)],

            'address_line_1'          => 'required|string|max:255',
            'address_line_2'          => 'nullable|string|max:255', 
            'city'                    => 'required|string|max:100',
            'state'                   => 'required|string|max:100',
            'pincode'                 => 'required|string|max:20',
            'country'                 => 'required|string|max:100',

            'shipping_address_line_1' => 'nullable|string|max:255',
            'shipping_address_line_2' => 'nullable|string|max:255',
            'shipping_city'           => 'nullable|string|max:100',
            'shipping_state'          => 'nullable|string|max:100',
            'shipping_pincode'        => 'nullable|string|max:20',
            'shipping_country'        => 'nullable|string|max:100',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $user) {

            $user->update([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'mobile'     => $validated['mobile'] ?? null,
                'email'      => $validated['email'],
                'gender'     => $validated['gender'],
                'dob'        => $validated['dob'],
            ]);

            $user->addresses()->updateOrCreate(
                ['type' => 'home'],
                [
                    'contact_name'   => $validated['first_name'] . ' ' . $validated['last_name'],
                    'contact_mobile' => $validated['mobile'] ?? 'N/A',
                    'address_line_1' => $validated['address_line_1'],
                    'address_line_2' => $validated['address_line_2'] ?? null,
                    'city'           => $validated['city'],
                    'state'          => $validated['state'],
                    'pincode'        => $validated['pincode'],
                    'country'        => $validated['country'],
                    'is_default'     => true,
                ]
            );

            // Shipping Address
            if (! empty($validated['shipping_address_line_1'])) {
                $user->addresses()->updateOrCreate(
                    ['type' => 'shipping'],
                    [
                        'contact_name'   => $validated['first_name'] . ' ' . $validated['last_name'],
                        'contact_mobile' => $validated['mobile'] ?? 'N/A',
                        'address_line_1' => $validated['shipping_address_line_1'],
                        'address_line_2' => $validated['shipping_address_line_2'] ?? null,
                        'city'           => $validated['shipping_city'],
                        'state'          => $validated['shipping_state'],
                        'pincode'        => $validated['shipping_pincode'],
                        'country'        => $validated['shipping_country'] ?? 'India',
                        'is_default'     => false,
                    ]
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
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
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

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}
