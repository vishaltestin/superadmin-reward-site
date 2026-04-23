<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function updateBusinessDetails(Request $request)
    {
        $validated = $request->validate([
            'gst_no'             => 'nullable|string|max:50',
            'pan_no'             => 'nullable|string|max:50',
            'industry'           => 'nullable|string|max:255',
            'address'            => 'nullable|string',
            'number_of_employee' => 'nullable|string',
        ]);

        $request->user()->company->update($validated);

        return response()->json([
            'message' => 'Business details updated successfully.',
            'company' => new CompanyResource($request->user()->company)
        ]);
    }

    public function updateStorefrontSettings(Request $request)
    {
        $company = $request->user()->company;

        $validated = $request->validate([
            'points_name'  => 'nullable|string|max:50',
            'terms_text'   => 'nullable|string',
            'privacy_text' => 'nullable|string',
            'social_links' => 'nullable|array',
            'logo'         => 'nullable|image|max:2048',
        ]);
      
        if ($request->hasFile('logo')) {
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $validated['logo'] = $request->file('logo')->store('companies/logos', 'public');
        }

        $company->update($validated);

        return response()->json([
            'message' => 'Storefront branding updated successfully.',
            'company' => new CompanyResource($company)
        ]);
    }

    public function updateCatalogVisibility(Request $request)
    {
        $validated = $request->validate([
            'hidden_category_ids'   => 'nullable|array',
            'hidden_category_ids.*' => 'integer',
            'hidden_product_ids'    => 'nullable|array',
            'hidden_product_ids.*'  => 'integer',
        ]);

        $request->user()->company->update($validated);

        return response()->json([
            'message' => 'Catalog visibility updated successfully.',
            'company' => new CompanyResource($request->user()->company)
        ]);
    }
}