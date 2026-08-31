<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
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

        $company = $request->user()->company;

        if ($request->filled('gst_no') || $request->filled('pan_no')) {
            if ($company->verification_status !== 'verified') {
                $validated['verification_status'] = 'submitted';
            }
        }

        $company->update($validated);

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
            'social_links' => 'nullable',
            'logo'         => 'nullable|image|max:2048',
        ]);

        if ($request->exists('social_links')) {
            $raw  = $request->input('social_links');
            $data = is_string($raw) ? json_decode($raw, true) : $raw;

            $validated['social_links'] = is_array($data)
                ? array_values(array_filter(
                array_map(fn($link) => [
                    'platform' => $link['platform'] ?? null,
                    'url'      => $link['url'] ?? null,
                ], $data),
                fn($link) => ! empty($link['platform']) && ! empty($link['url'])
            ))
                : [];
        }

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

    public function updateCategoryPreferences(Request $request)
    {
        $company = $request->user()->company;

        $validated = $request->validate([
            'order'                => ['nullable', 'array'],
            'order.*'              => ['integer'],
            'statuses'             => ['nullable', 'array'],
            'statuses.*.id'        => ['required', 'integer'],
            'statuses.*.is_active' => ['nullable', 'boolean'],
        ]);

        $assignedIds = $company->categories()->pluck('categories.id')->toArray();

        if (! empty($validated['order'])) {
            $order = array_values(array_values(array_unique($validated['order'])));
            $order = array_intersect($order, $assignedIds);

            foreach ($order as $index => $categoryId) {
                $company->categories()->updateExistingPivot($categoryId, [
                    'sort_order' => $index + 1,
                ]);
            }
        }

        if (! empty($validated['statuses'])) {
            foreach ($validated['statuses'] as $status) {
                if (! in_array($status['id'], $assignedIds)) {
                    continue;
                }

                $company->categories()->updateExistingPivot($status['id'], [
                    'is_active' => $status['is_active'] ?? null,
                ]);
            }
        }

        return response()->json(array_merge(
            ['message' => 'Category preferences updated successfully.'],
            $this->buildCatalogConfig($company)
        ));
    }

    public function getCatalogConfig(Request $request)
    {
        return response()->json(
            $this->buildCatalogConfig($request->user()->company)
        );
    }

    private function buildCatalogConfig(Company $company): array
    {
        $multiplier = (float) ($company->point_multiplier ?? 1.00);

        $companyBulkTiers = \App\Models\CompanyProductTierPrice::where('company_id', $company->id)->get();

        $categories = $company->categoriesByDisplayOrder()
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
            ->selectRaw('categories.is_active as global_is_active')
            ->selectRaw('category_company.is_active as pivot_is_active')
            ->selectRaw('category_company.sort_order as pivot_sort_order')
            ->get();

        $transformedCategories = $categories->map(function ($category) use ($multiplier, $company, $companyBulkTiers) {
            $transformProduct = function ($product) use ($multiplier, $company, $companyBulkTiers) {
                $productPivot = $product->customCompanies->first()?->pivot;

                $finalName  = ($productPivot && ! empty($productPivot->override_name)) ? $productPivot->override_name : $product->name;
                $finalImage = ($productPivot && ! empty($productPivot->override_image)) ? $productPivot->override_image : $product->main_image;
                $finalPrice = ($productPivot && is_numeric($productPivot->override_selling_price)) ? $productPivot->override_selling_price : $product->selling_price;
                $finalMrp   = ($productPivot && is_numeric($productPivot->override_mrp)) ? $productPivot->override_mrp : $product->mrp;

                $resolvedTiers = \App\Services\PricingService::resolveTiers(
                    $product,
                    $company,
                    $companyBulkTiers
                );

                return [
                    'id'            => $product->id,
                    'category_id'   => $product->category_id,
                    'name'          => $finalName,
                    'main_image'    => $finalImage ? asset('storage/' . $finalImage) : null,
                    'mrp'           => (float) $finalMrp,
                    'selling_price' => (float) $finalPrice,
                    'has_variants'  => $product->variants->count() > 0,

                    'variants'      => $product->variants->map(function ($v) use ($multiplier, $productPivot, $product) {
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

                    'tier_prices'   => $resolvedTiers->map(function ($t) {
                        return [
                            'variant_id'    => $t->product_variant_id,
                            'min_quantity'  => $t->min_quantity,
                            'selling_price' => (float) $t->selling_price,
                        ];
                    })->values()->toArray(),
                ];
            };

            $pivotIsActive  = $category->pivot_is_active !== null ? (bool) $category->pivot_is_active : null;
            $globalIsActive = (bool) $category->global_is_active;

            return [
                'id'                 => $category->id,
                'name'               => $category->name,
                'parent_id'          => $category->parent_id,
                'global_is_active'   => $globalIsActive,
                'pivot_is_active'    => $pivotIsActive,
                'is_active'          => $pivotIsActive ?? $globalIsActive,
                'pivot_sort_order'   => $category->pivot_sort_order,
                'primary_products'   => $category->primaryProducts->map($transformProduct),
                'secondary_products' => $category->secondaryProducts->map($transformProduct),
            ];
        });

        return [
            'categories'          => $transformedCategories,
            'hidden_category_ids' => $company->hidden_category_ids ?? [],
            'hidden_product_ids'  => $company->hidden_product_ids ?? [],
        ];
    }
}
