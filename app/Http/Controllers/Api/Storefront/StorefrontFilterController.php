<?php
namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorefrontFilterController extends Controller
{
    private function resolveTenant(string $slug): Company
    {
        return Company::where('alias', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function index(Request $request, $slug)
    {
        $company    = $this->resolveTenant($slug);
        $multiplier = (float) ($company->point_multiplier ?? 1.00);

        $allowedCategoryIds = $company->activeCategoryIds();
        $hiddenCategoryIds  = $company->hidden_category_ids ?? [];
        $hiddenProductIds   = $company->hidden_product_ids ?? [];

        $query = Product::query()
            ->leftJoin('company_product', function ($join) use ($company) {
                $join->on('products.id', '=', 'company_product.product_id')
                    ->where('company_product.company_id', '=', $company->id);
            })
            ->select('products.*')
            ->where('products.is_active', true)
            ->whereIn('products.category_id', $allowedCategoryIds)
            ->whereNotIn('products.category_id', $hiddenCategoryIds)
            ->whereNotIn('products.id', $hiddenProductIds);

        $query->whereDoesntHave('customCompanies', function ($q) use ($company) {
            $q->where('company_id', $company->id)->where('is_excluded', true);
        });

        if ($request->filled('q')) {
            $queryText = (string) $request->input('q');
            $escaped   = addcslashes($queryText, '%_\\\\');

            $query->where(function ($sub) use ($escaped, $queryText) {
                $sub->where('products.name', 'LIKE', "%{$escaped}%")
                    ->orWhere('products.short_description', 'LIKE', "%{$escaped}%")
                    ->orWhere('company_product.override_name', 'LIKE', "%{$escaped}%")
                    ->orWhereJsonContains('products.tags', $queryText);
            });
        }

        if ($request->filled('category_slug')) {
            $query->whereHas('primaryCategory', function ($q) use ($request) {
                $q->where('slug', $request->input('category_slug'));
            });
        }

        if ($request->filled('min_price')) {
            $query->where(DB::raw('COALESCE(company_product.override_selling_price, products.selling_price)'), '>=', (float) $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where(DB::raw('COALESCE(company_product.override_selling_price, products.selling_price)'), '<=', (float) $request->input('max_price'));
        }

        $sortBy = $request->input('sort_by', 'default');
        switch ($sortBy) {
            case 'price_asc':
                $query->orderBy(DB::raw('COALESCE(company_product.override_selling_price, products.selling_price)'), 'asc');
                break;
            case 'price_desc':
                $query->orderBy(DB::raw('COALESCE(company_product.override_selling_price, products.selling_price)'), 'desc');
                break;
            case 'newest':
                $query->orderBy('products.created_at', 'desc');
                break;
            case 'default':
            default:
                $query->orderBy('products.sort_order', 'asc');
                break;
        }

        $query->with(['brand:id,name,logo', 'customCompanies' => function ($q) use ($company) {
            $q->where('company_id', $company->id);
        }, 'variants' => function ($q) {
            $q->where('is_active', true);
        }]);

        $perPage  = $request->integer('per_page', 16);
        $products = $query->paginate($perPage);

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
}
