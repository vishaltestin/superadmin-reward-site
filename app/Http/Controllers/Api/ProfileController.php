<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function me(Request $request)
    {
        // Eager load the relationships needed for the Resource
        return new UserResource($request->user()->load(['company', 'rewardeeProfile']));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'mobile' => 'nullable|string|max:20',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)], 
            'password' => 'nullable|string|min:8|confirmed', 
        ]);

        $user->fill([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'mobile' => $validated['mobile'],
            'email' => $validated['email'],
        ]);

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Handle dynamic JSON data if they are a rewardee
        if ($user->user_type === 'rewardee' && $request->has('custom_data')) {
            $validatedData = $request->validate(['custom_data' => 'array']);
            
            if ($user->rewardeeProfile) {
                $existingData = $user->rewardeeProfile->vertical_data ?? [];
                $user->rewardeeProfile()->update([
                    'vertical_data' => array_merge($existingData, $validatedData['custom_data'])
                ]);
            }
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => new UserResource($user->load(['company', 'rewardeeProfile'])),
        ]);
    }
}