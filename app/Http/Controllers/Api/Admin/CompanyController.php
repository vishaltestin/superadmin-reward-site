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


public function getCatalogConfig(Request $request)
{
    $company = $request->user()->company;

    $categories = $company->categories()
        ->where('is_active', true)
        ->with(['primaryProducts' => function($query) use ($company) {
            $query->where('is_active', true)
                  ->whereDoesntHave('customCompanies', function($q) use ($company) {
                      $q->where('company_id', $company->id)->where('is_excluded', true);
                  })
                  ->select('products.id', 'products.category_id', 'products.name', 'products.main_image', 'products.selling_price', 'products.mrp');
        }])
        ->with(['secondaryProducts' => function($query) use ($company) {
            $query->where('is_active', true)
                  ->whereDoesntHave('customCompanies', function($q) use ($company) {
                      $q->where('company_id', $company->id)->where('is_excluded', true);
                  })
                  ->select('products.id', 'products.name', 'products.main_image', 'products.selling_price', 'products.mrp'); 
        }])
        ->select('categories.id', 'categories.name', 'categories.parent_id')
        ->get();

    return response()->json([
        'categories' => $categories,
        'hidden_category_ids' => $company->hidden_category_ids ?? [],
        'hidden_product_ids' => $company->hidden_product_ids ?? [],
    ]);
}
}