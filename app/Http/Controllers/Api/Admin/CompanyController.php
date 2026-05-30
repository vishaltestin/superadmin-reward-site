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
            'company' => new CompanyResource($request->user()->company),
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
            'company' => new CompanyResource($company),
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
            'company' => new CompanyResource($request->user()->company),
        ]);
    }

    // public function getCatalogConfig(Request $request)
    // {
    //     $company = $request->user()->company;

    //     $categories = $company->categories()
    //         ->where('is_active', true)
    //         ->with(['primaryProducts' => function ($query) use ($company) {
    //             $query->where('is_active', true)
    //                 ->whereDoesntHave('customCompanies', function ($q) use ($company) {
    //                     $q->where('company_id', $company->id)->where('is_excluded', true);
    //                 })
    //                 ->select('products.id', 'products.category_id', 'products.name', 'products.main_image', 'products.selling_price', 'products.mrp');
    //         }])
    //         ->with(['secondaryProducts' => function ($query) use ($company) {
    //             $query->where('is_active', true)
    //                 ->whereDoesntHave('customCompanies', function ($q) use ($company) {
    //                     $q->where('company_id', $company->id)->where('is_excluded', true);
    //                 })
    //                 ->select('products.id', 'products.name', 'products.main_image', 'products.selling_price', 'products.mrp');
    //         }])
    //         ->select('categories.id', 'categories.name', 'categories.parent_id')
    //         ->get();

    //     return response()->json([
    //         'categories'          => $categories,
    //         'hidden_category_ids' => $company->hidden_category_ids ?? [],
    //         'hidden_product_ids'  => $company->hidden_product_ids ?? [],
    //     ]);
    // }

    public function getCatalogConfig(Request $request)
    {
        $company    = $request->user()->company;
        $multiplier = (float) ($company->point_multiplier ?? 1.00);

        $companyBulkTiers = \App\Models\CompanyProductTierPrice::where('company_id', $company->id)->get();

        $categories = $company->categories()
            ->where('categories.is_active', true)
            ->with([
                'primaryProducts'   => function ($query) use ($company) {
                    $query->where('products.is_active', true)
                        ->whereDoesntHave('customCompanies', function ($q) use ($company) {
                            $q->where('company_id', $company->id)->where('is_excluded', true);
                        })
                        ->with([
                            'customCompanies' => function ($q) use ($company) {
                                $q->where('company_id', $company->id);
                            },
                            'variants'        => function ($q) use ($company) {
                                $q->where('is_active', true)
                                    ->with(['companyOverrides' => function ($subQ) use ($company) {
                                        $subQ->where('company_id', $company->id);
                                    }]);
                            },
                            'tierPrices',
                        ]);
                },
                'secondaryProducts' => function ($query) use ($company) {
                    $query->where('products.is_active', true)
                        ->whereDoesntHave('customCompanies', function ($q) use ($company) {
                            $q->where('company_id', $company->id)->where('is_excluded', true);
                        })
                        ->with([
                            'customCompanies' => function ($q) use ($company) {
                                $q->where('company_id', $company->id);
                            },
                            'variants'        => function ($q) use ($company) {
                                $q->where('is_active', true)
                                    ->with(['companyOverrides' => function ($subQ) use ($company) {
                                        $subQ->where('company_id', $company->id);
                                    }]);
                            },
                            'tierPrices',
                        ]);
                },
            ])
            ->select('categories.id', 'categories.name', 'categories.parent_id')
            ->get();

        $transformedCategories = $categories->map(function ($category) use ($multiplier, $companyBulkTiers) {
            $transformProduct = function ($product) use ($multiplier, $companyBulkTiers) {
                $productPivot = $product->customCompanies->first()?->pivot;

                $finalName  = ($productPivot && ! empty($productPivot->override_name)) ? $productPivot->override_name : $product->name;
                $finalImage = ($productPivot && ! empty($productPivot->override_image)) ? $productPivot->override_image : $product->main_image;
                $finalPrice = ($productPivot && is_numeric($productPivot->override_selling_price)) ? $productPivot->override_selling_price : $product->selling_price;
                $finalMrp   = ($productPivot && is_numeric($productPivot->override_mrp)) ? $productPivot->override_mrp : $product->mrp;

                $productCompanyTiers = $companyBulkTiers->where('product_id', $product->id);
                $baseCompanyTiers    = $productCompanyTiers->whereNull('product_variant_id');

                $resolvedTiers = collect();
                if ($baseCompanyTiers->isNotEmpty()) {
                    $resolvedTiers = $resolvedTiers->concat($baseCompanyTiers);
                } else {
                    $resolvedTiers = $resolvedTiers->concat($product->tierPrices->whereNull('product_variant_id'));
                }

                return [
                    'id'             => $product->id,
                    'category_id'    => $product->category_id,
                    'name'           => $finalName,
                    'main_image' => $finalImage ? asset('storage/' . $finalImage) : null,
                    'mrp'            => (float) $finalMrp,
                    'selling_price'  => (float) $finalPrice,
                    'has_variants'   => $product->variants->count() > 0,

                    'variants'       => $product->variants->map(function ($v) use ($multiplier, $productPivot, $product) {
                        $variantPivot = $v->companyOverrides->first()?->pivot;

                        $vPrice = ($variantPivot && is_numeric($variantPivot->override_selling_price)) ? $variantPivot->override_selling_price : ($v->selling_price ?? $productPivot?->override_selling_price ?? $product->selling_price);
                        $vMrp   = ($variantPivot && is_numeric($variantPivot->override_mrp)) ? $variantPivot->override_mrp : ($v->mrp ?? $productPivot?->override_mrp ?? $product->mrp);
                        $vImage = ($variantPivot && ! empty($variantPivot->override_image)) ? $variantPivot->override_image : ($v->image ?? $productPivot?->override_image ?? $product->main_image);

                        return [
                            'id'            => $v->id,
                            'name'          => $v->name,
                            'sku'           => $v->sku,
                            'image_url'     => $vImage ? asset('storage/' . $vImage) : null,
                            'mrp'           => (float) $vMrp,
                            'selling_price' => (float) $vPrice,
                        ];
                    })->values()->toArray(),

                    'tier_prices'    => $resolvedTiers->map(function ($t) {
                        return [
                            'variant_id'    => $t->product_variant_id,
                            'min_quantity'  => $t->min_quantity,
                            'selling_price' => (float) $t->selling_price,
                        ];
                    })->values()->toArray(),
                ];
            };

            return [
                'id'                 => $category->id,
                'name'               => $category->name,
                'parent_id'          => $category->parent_id,
                'primary_products'   => $category->primaryProducts->map($transformProduct),
                'secondary_products' => $category->secondaryProducts->map($transformProduct),
            ];
        });

        return response()->json([
            'categories'          => $transformedCategories,
            'hidden_category_ids' => $company->hidden_category_ids ?? [],
            'hidden_product_ids'  => $company->hidden_product_ids ?? [],
        ]);
    }
}
