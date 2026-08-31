<?php
namespace App\Services;

use App\Models\Company;
use App\Models\CompanyProductTierPrice;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * SINGLE source of truth for what anything costs.
 *
 * Before this service existed, three copies of pricing logic drifted apart:
 *   - StorefrontCatalogController (product page)  → company tiers, global fallback
 *   - StorefrontCheckoutController (cart charge)  → GLOBAL TIERS ONLY  ← the bug
 *   - CompanyController::getCatalogConfig (admin) → company/global base tiers only
 * so the storefront displayed one price and charged another whenever a company
 * had negotiated tier prices.
 *
 * Precedence rules (identical to what the product page always displayed):
 *   Base price (qty < any tier):
 *     variant override → variant price → product override → product price
 *   Base tiers (variant NULL):
 *     company tiers if the company has any, else global tiers
 *   Variant tiers:
 *     company tiers for that variant if any;
 *     else global tiers for that variant, but ONLY when the company has no
 *     base tiers of its own
 *   Tier applied to a line:
 *     among tiers matching (variant IS NULL OR = selected variant) with
 *     min_quantity <= quantity, the highest min_quantity wins
 */
class PricingService
{
    /**
     * All effective tier rows for a product/company (base + per-variant),
     * already resolved to the winning source (company vs global).
     *
     * $preloadedCompanyTiers lets batch consumers (e.g. admin catalog config)
     * avoid an N+1 by passing tiers for many products fetched in one query.
     */
    public static function resolveTiers(Product $product, Company $company, ?Collection $preloadedCompanyTiers = null): Collection
    {
        $companyTiers = ($preloadedCompanyTiers !== null)
            ? $preloadedCompanyTiers->where('product_id', $product->id)
            : CompanyProductTierPrice::where('company_id', $company->id)
            ->where('product_id', $product->id)
            ->get();

        $globalTiers = $product->relationLoaded('tierPrices')
            ? $product->tierPrices
            : $product->tierPrices()->get();

        $resolved = collect();

        // Base tiers (variant NULL): company first, global fallback.
        $baseCompanyTiers = $companyTiers->whereNull('product_variant_id');
        if ($baseCompanyTiers->isNotEmpty()) {
            $resolved = $resolved->concat($baseCompanyTiers);
        } else {
            $resolved = $resolved->concat($globalTiers->whereNull('product_variant_id'));
        }

        // Variant tiers: company first; global only when no company base tiers exist.
        foreach ($product->variants as $variant) {
            $variantCompanyTiers = $companyTiers->where('product_variant_id', $variant->id);

            if ($variantCompanyTiers->isNotEmpty()) {
                $resolved = $resolved->concat($variantCompanyTiers);
            } elseif ($baseCompanyTiers->isEmpty()) {
                $resolved = $resolved->concat($globalTiers->where('product_variant_id', $variant->id));
            }
        }

        return $resolved->values();
    }

    /**
     * The effective unit price for a given quantity — used by cart checkout
     * and magic-link claims, so what is CHARGED always equals what the product
     * page DISPLAYS.
     */
    public static function unitPrice(Product $product, ?ProductVariant $variant, Company $company, int $quantity): float
    {
        // Query the pivots for THIS company directly — never trust how the
        // caller eager-loaded the relations (an unconstrained load could
        // return another company's override).
        $productPivot = $product->customCompanies()
            ->where('company_id', $company->id)
            ->first()?->pivot;

        $unitPrice = (float) ($productPivot?->override_selling_price ?? $product->selling_price);

        if ($variant) {
            $variantPivot = $variant->companyOverrides()
                ->where('company_id', $company->id)
                ->first()?->pivot;

            $unitPrice = (float) ($variantPivot?->override_selling_price ?? $variant->selling_price ?? $unitPrice);
        }

        $applicableTier = self::resolveTiers($product, $company)
            ->filter(function ($tier) use ($variant) {
                return $tier->product_variant_id === null
                    || ($variant && $tier->product_variant_id == $variant->id);
            })
            ->filter(fn($tier) => $tier->min_quantity <= $quantity)
            ->sortByDesc('min_quantity')
            ->first();

        if ($applicableTier) {
            $unitPrice = (float) $applicableTier->selling_price;
        }

        return round($unitPrice, 2);
    }
}
