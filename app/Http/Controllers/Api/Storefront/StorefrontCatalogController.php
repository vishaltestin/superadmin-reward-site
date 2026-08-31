<?php
namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Services\PricingService;
use Illuminate\Http\Request;

class StorefrontCatalogController extends Controller
{
    private function resolveTenant(string $slug): Company
    {
        return Company::where('alias', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function categories(Request $request, $slug)
    {
        $company = $this->resolveTenant($slug);

        $categories = $company->categoriesByDisplayOrder()
            ->whereIn('categories.id', $company->activeCategoryIds())
            ->get([
                'categories.id',
                'categories.parent_id',
                'categories.name',
                'categories.slug',
                'categories.image',
                'categories.description',
            ]);

        return response()->json(['data' => $categories]);
    }

    public function products(Request $request, $slug)
    {
        $company    = $this->resolveTenant($slug);
        $multiplier = (float) ($company->point_multiplier ?? 1.00);

        $allowedCategoryIds = $company->activeCategoryIds();
        $hiddenCategoryIds  = $company->hidden_category_ids ?? [];
        $hiddenProductIds   = $company->hidden_product_ids ?? [];

        $query = Product::where('is_active', true)
            ->whereIn('category_id', $allowedCategoryIds)
            ->whereNotIn('category_id', $hiddenCategoryIds)
            ->whereNotIn('id', $hiddenProductIds)
            ->with(['brand:id,name,logo', 'variants' => function ($q) use ($company) {
                $q->where('is_active', true)
                    ->with(['companyOverrides' => function ($subQ) use ($company) {
                        $subQ->where('company_id', $company->id);
                    }]);
            }]);

        if ($request->has('category_slug')) {
            $query->whereHas('primaryCategory', function ($q) use ($request) {
                $q->where('slug', $request->category_slug);
            });
        }

        $query->whereDoesntHave('customCompanies', function ($q) use ($company) {
            $q->where('company_id', $company->id)->where('is_excluded', true);
        });

        $query->with(['customCompanies' => function ($q) use ($company) {
            $q->where('company_id', $company->id);
        }]);

        $products = $query->orderBy('sort_order', 'asc')->paginate(16);

        $products->through(function ($product) use ($multiplier) {
            $productPivot = $product->customCompanies->first()?->pivot;

            $finalName  = $productPivot?->override_name ?? $product->name;
            $finalImage = $productPivot?->override_image ?? $product->main_image;

            $fiatMrp          = $productPivot?->override_mrp ?? $product->mrp;
            $fiatSellingPrice = $productPivot?->override_selling_price ?? $product->selling_price;

            return [
                'id'                => $product->id,
                'name'              => $finalName,
                'slug'              => $product->slug,
                'sku'               => $product->sku,
                'type'              => $product->type,
                'main_image_url'    => $finalImage ? asset('storage/' . $finalImage) : null,
                'brand'             => $product->brand?->name,
                'mrp'               => (float) $fiatMrp,
                'selling_price'     => (float) $fiatSellingPrice,
                'points_equivalent' => (int) ceil((float) $fiatSellingPrice * $multiplier),
                'short_description' => $product->short_description,
                'has_variants'      => $product->variants->count() > 0,
            ];
        });

        return response()->json($products);
    }

    public function productDetail(Request $request, $slug, $productSlug)
    {
        $company    = $this->resolveTenant($slug);
        $multiplier = (float) ($company->point_multiplier ?? 1.00);

        if (in_array($productSlug, $company->hidden_product_ids ?? [])) {
            return response()->json(['message' => 'Product unavailable.'], 404);
        }

        $allowedCategoryIds = $company->activeCategoryIds();
        $hiddenCategoryIds  = $company->hidden_category_ids ?? [];

        $product = Product::where('slug', $productSlug)
            ->where('is_active', true)
            ->whereIn('category_id', $allowedCategoryIds)
            ->whereNotIn('category_id', $hiddenCategoryIds)
            ->whereDoesntHave('customCompanies', function ($q) use ($company) {
                $q->where('company_id', $company->id)->where('is_excluded', true);
            })
            ->with(['brand', 'variants' => function ($q) use ($company) {
                $q->where('is_active', true)
                    ->with(['companyOverrides' => function ($subQ) use ($company) {
                        $subQ->where('company_id', $company->id);
                    }]);
            }, 'tierPrices', 'customCompanies' => function ($q) use ($company) {
                $q->where('company_id', $company->id);
            }])
            ->firstOrFail();

        $productPivot = $product->customCompanies->first()?->pivot;

        $finalName        = $productPivot?->override_name ?? $product->name;
        $finalImage       = $productPivot?->override_image ?? $product->main_image;
        $fiatMrp          = $productPivot?->override_mrp ?? $product->mrp;
        $fiatSellingPrice = $productPivot?->override_selling_price ?? $product->selling_price;

        $resolvedTiers = PricingService::resolveTiers($product, $company);

        return response()->json([
            'data' => [
                'id'                   => $product->id,
                'name'                 => $finalName,
                'slug'                 => $product->slug,
                'sku'                  => $product->sku,
                'type'                 => $product->type,
                'main_image_url'       => $finalImage ? asset('storage/' . $finalImage) : null,
                'gallery_images'       => array_map(fn($img) => asset('storage/' . $img), $product->gallery_images ?? []),
                'mrp'                  => (float) $fiatMrp,
                'selling_price'        => (float) $fiatSellingPrice,
                'points_equivalent'    => (int) ceil((float) $fiatSellingPrice * $multiplier),
                'has_variants'         => $product->variants->count() > 0,
                'short_description'    => $product->short_description,
                'long_description'     => $product->long_description,
                'key_features'         => $product->key_features ?? [],
                'specifications'       => empty($product->specifications) ? (object) [] : $product->specifications,
                'terms_and_conditions' => $product->terms_and_conditions,
                'brand'                => $product->brand?->name,
                'type_data'            => $product->type_data,
                'video_url'            => $product->video_url,

                'variants'             => $product->variants->map(function ($v) use ($multiplier, $productPivot, $product) {
                    $variantPivot = $v->companyOverrides->first()?->pivot;

                    $finalVariantSellingPrice = $variantPivot?->override_selling_price ?? $v->selling_price ?? $productPivot?->override_selling_price ?? $product->selling_price;

                    $finalVariantMrp = $variantPivot?->override_mrp ?? $v->mrp ?? $productPivot?->override_mrp ?? $product->mrp;

                    $finalVariantImage = $variantPivot?->override_image ?? $v->image ?? $productPivot?->override_image ?? $product->main_image;

                    return [
                        'id'                => $v->id,
                        'name'              => $v->name,
                        'sku'               => $v->sku,
                        'image_url'         => $finalVariantImage ? asset('storage/' . $finalVariantImage) : null,
                        'gallery_images'    => array_map(fn($img) => asset('storage/' . $img), $v->gallery_images ?? []),
                        'mrp'               => (float) $finalVariantMrp,
                        'selling_price'     => (float) $finalVariantSellingPrice,
                        'points_equivalent' => (int) ceil((float) $finalVariantSellingPrice * $multiplier),
                        'stock_quantity'    => $v->stock_quantity,
                        'attributes'        => $v->attributes,
                    ];
                }),

                'tier_prices'          => $resolvedTiers->map(function ($t) use ($multiplier) {
                    return [
                        'variant_id'        => $t->product_variant_id,
                        'min_quantity'      => $t->min_quantity,
                        'selling_price'     => (float) $t->selling_price,
                        'points_equivalent' => (int) ceil((float) $t->selling_price * $multiplier),
                    ];
                }),
            ],
        ]);
    }

    public function search(Request $request, $slug)
    {
        $company    = $this->resolveTenant($slug);
        $multiplier = (float) ($company->point_multiplier ?? 1.00);
        $queryText  = $request->query('q');

        if (empty($queryText)) {
            return response()->json(['data' => []]);
        }

        $allowedCategoryIds = $company->activeCategoryIds();
        $hiddenCategoryIds  = $company->hidden_category_ids ?? [];
        $hiddenProductIds   = $company->hidden_product_ids ?? [];

        $query = Product::where('is_active', true)
            ->whereIn('category_id', $allowedCategoryIds)
            ->whereNotIn('category_id', $hiddenCategoryIds)
            ->whereNotIn('id', $hiddenProductIds)
            ->whereDoesntHave('customCompanies', function ($q) use ($company) {
                $q->where('company_id', $company->id)->where('is_excluded', true);
            })
            ->with(['customCompanies' => function ($q) use ($company) {
                $q->where('company_id', $company->id);
            }]);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $escaped = addcslashes($queryText, '%_\\\\');

        $query->where(function ($q) use ($escaped, $queryText, $company) {
            $q->where('name', 'LIKE', "%{$escaped}%")
                ->orWhere('short_description', 'LIKE', "%{$escaped}%")
                ->orWhereJsonContains('tags', $queryText);

            $q->orWhereHas('customCompanies', function ($subQ) use ($escaped, $company) {
                $subQ->where('company_id', $company->id)
                    ->where('override_name', 'LIKE', "%{$escaped}%");
            });
        });

        $products = $query->take(20)->get();

        $mappedResults = $products->map(function ($product) use ($multiplier) {
            $pivot            = $product->customCompanies->first()?->pivot;
            $finalName        = $pivot?->override_name ?? $product->name;
            $finalImage       = $pivot?->override_image ?? $product->main_image;
            $fiatMrp          = $pivot?->override_mrp ?? $product->mrp;
            $fiatSellingPrice = $pivot?->override_selling_price ?? $product->selling_price;

            return [
                'id'                => $product->id,
                'name'              => $finalName,
                'slug'              => $product->slug,
                'main_image_url'    => $finalImage ? asset('storage/' . $finalImage) : null,
                'mrp'               => (float) $fiatMrp,
                'selling_price'     => (float) $fiatSellingPrice,
                'points_equivalent' => (int) ceil((float) $fiatSellingPrice * $multiplier),
            ];
        });

        return response()->json(['data' => $mappedResults]);
    }
}
