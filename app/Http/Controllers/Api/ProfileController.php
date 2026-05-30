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

        $user->load(['company.wallet', 'wallet']);

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
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'required|string|max:255',
            'Gender'            => 'required|string|max:50',
            'dob'               => 'required|date',
            'mobile'            => 'nullable|string|max:20',
            'email'             => ['required', 'email', Rule::unique('users')->ignore($user->id)],

            'Address'           => 'required|string|max:255',
            'City'              => 'required|string|max:100',
            'State'             => 'required|string|max:100',
            'Landmark'          => 'nullable|string|max:255',
            'PinCode'           => 'required|string|max:20',
            'Country'           => 'required|string|max:100',

            'Shipping_Address'  => 'nullable|string|max:255',
            'Shipping_City'     => 'nullable|string|max:100',
            'Shipping_State'    => 'nullable|string|max:100',
            'Shipping_Landmark' => 'nullable|string|max:255',
            'Shipping_PinCode'  => 'nullable|string|max:20',
            'Shipping_Country'  => 'nullable|string|max:100',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $user) {

            $user->update([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'mobile'     => $validated['mobile'] ?? null,
                'email'      => $validated['email'],
                'gender'     => $validated['Gender'],
                'dob'        => $validated['dob'],
            ]);

            $user->addresses()->updateOrCreate(
                ['type' => 'home'],
                [
                    'contact_name'   => $validated['first_name'] . ' ' . $validated['last_name'],
                    'contact_mobile' => $validated['mobile'] ?? 'N/A',
                    'address_line_1' => $validated['Address'],
                    'address_line_2' => $validated['Landmark'] ?? null,
                    'city'           => $validated['City'],
                    'state'          => $validated['State'],
                    'pincode'        => $validated['PinCode'],
                    'country'        => $validated['Country'],
                    'is_default'     => true,
                ]
            );

            if (! empty($validated['Shipping_Address'])) {
                $user->addresses()->updateOrCreate(
                    ['type' => 'shipping'],
                    [
                        'contact_name'   => $validated['first_name'] . ' ' . $validated['last_name'],
                        'contact_mobile' => $validated['mobile'] ?? 'N/A',
                        'address_line_1' => $validated['Shipping_Address'],
                        'address_line_2' => $validated['Shipping_Landmark'] ?? null,
                        'city'           => $validated['Shipping_City'],
                        'state'          => $validated['Shipping_State'],
                        'pincode'        => $validated['Shipping_PinCode'],
                        'country'        => $validated['Shipping_Country'] ?? 'India',
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
