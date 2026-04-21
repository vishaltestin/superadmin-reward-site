<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function updateBusiness(Request $request)
    {
        $user = $request->user();

        // Strict Role Check
        if (!in_array($user->user_type, ['business_head', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized. Only Business Heads can update company details.'], 403);
        }

        $company = $user->company;

        if (!$company) {
            return response()->json(['message' => 'No company associated with this account.'], 404);
        }

        $validated = $request->validate([
            'gst_no' => 'nullable|string|max:50',
            'pan_no' => 'nullable|string|max:50',
            'industry' => 'nullable|string|max:255',
            'address' => 'nullable|string',
        ]);

        $company->update($validated);

        return response()->json([
            'message' => 'Company profile updated successfully.',
            'company' => new CompanyResource($company)
        ]);
    }
}