<?php
namespace App\Models;

use App\Traits\HasWallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes, HasWallet;

    protected $fillable = [
        'name',
        'number_of_employee',
        'gst_no',
        'pan_no',
        'industry',
        'address',
        'alias',
        'logo',
        'points_name',
        'point_multiplier',
        'is_active',
        'is_approved',
        'verification_status',
        'social_links',
        'terms_text',
        'privacy_text',
        'hidden_category_ids',
        'hidden_product_ids',
    ];

    protected function casts(): array
    {
        return [
            'is_active'           => 'boolean',
            'is_approved'         => 'boolean',
            'point_multiplier'    => 'float',
            'social_links'        => 'array',
            'hidden_category_ids' => 'array',
            'hidden_product_ids'  => 'array',
        ];
    }
    protected static function booted()
    {
        // Listen for updates to the company record
        static::updated(function ($company) {
            // If the 'is_approved' column was just changed, AND it is now true
            if ($company->wasChanged('is_approved') && $company->is_approved) {
                // Create the wallet safely (firstOrCreate prevents accidental duplicates)
                $company->wallet()->firstOrCreate([], ['balance' => 0.00]);
            }
        });
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class)
            ->withPivot(['is_active', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * Categories that are effectively VISIBLE to this company's storefront:
     * the per-company pivot override wins when set, otherwise the platform's
     * global categories.is_active applies. COALESCE does exactly that in SQL.
     *
     * NOTE: this query does NOT include the parent-chain cascade — that lives
     * in activeCategoryIds(), which is the single source of truth storefront
     * queries should scope by.
     */
    public function activeCategories()
    {
        return $this->categories()
            ->whereRaw('COALESCE(category_company.is_active, categories.is_active) = 1');
    }

    /**
     * Single source of truth for catalog scoping — every storefront query
     * (navigation, catalog listing, filters, search, product detail, claim
     * catalog) resolves its allowed category ids through here.
     *
     * A category is visible only when BOTH hold:
     *   1. it is effectively active: the HR pivot override wins when present,
     *      otherwise the platform's global is_active; entries listed in the
     *      company's hidden_category_ids are always inactive;
     *   2. its full ancestor chain inside the company's assigned set is
     *      visible too (parent cascade). Ancestors NOT assigned to the
     *      company are ignored — their children already render as top-level,
     *      mirroring the HR curation UI.
     *
     * The cascade is computed at read time and never written back, so each
     * category keeps its own on/off setting: unhiding a parent restores
     * children exactly as HR left them (individually-hidden ones stay
     * hidden).
     *
     * @return int[]
     */
    public function activeCategoryIds(): array
    {
        $hiddenIds = array_map('intval', $this->hidden_category_ids ?? []);

        // Every assigned category with what effective visibility needs:
        // the pivot override (HR), the platform global flag, and the parent link.
        $rows = $this->categories()
            ->get(['categories.id', 'categories.parent_id', 'categories.is_active']);

        $isOwnActive = [];
        $parentIdOf  = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;

            $override = $row->pivot->is_active;

            $ownActive = $override !== null
                ? (int) $override === 1
                : (bool) $row->is_active;

            if ($ownActive && in_array($id, $hiddenIds, true)) {
                $ownActive = false;
            }

            $isOwnActive[$id] = $ownActive;
            $parentIdOf[$id]  = $row->parent_id !== null ? (int) $row->parent_id : null;
        }

        $visible = [];

        $isVisible = function (int $id) use (&$isVisible, &$visible, &$isOwnActive, &$parentIdOf): bool {
            if (array_key_exists($id, $visible)) {
                return $visible[$id];
            }

            // Seed false before walking up: guards against legacy parent
            // cycles (a -> b -> a) so recursion always terminates.
            $visible[$id] = false;

            $result = $isOwnActive[$id] ?? false;

            if ($result) {
                $parentId = $parentIdOf[$id] ?? null;

                // Cascade only through parents assigned to this company.
                if ($parentId !== null && array_key_exists($parentId, $isOwnActive)) {
                    $result = $isVisible($parentId);
                }
            }

            return $visible[$id] = $result;
        };

        $ids = [];

        foreach (array_keys($isOwnActive) as $id) {
            if ($isVisible($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Display order for this company's categories: HR's per-company sort_order
     * when the pivot carries one, otherwise the platform's sort_order. When HR
     * saves an order we write pivot rows for ALL assigned categories, so the
     * two schemes never mix inside one storefront.
     */
    public function categoriesByDisplayOrder()
    {
        return $this->categories()
            ->orderByRaw('COALESCE(category_company.sort_order, categories.sort_order) asc');
    }

    public function verticals()
    {
        return $this->belongsToMany(Vertical::class);
    }

    public function customProducts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot([
                'is_excluded',
                'override_name',
                'override_image',
                'override_mrp',
                'override_selling_price',
            ])
            ->withTimestamps();
    }

    /**
     * Get all orders placed by employees of this company.
     */
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }
    public function variantOverrides(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'company_product_variant')
            ->withPivot(['override_image', 'override_mrp', 'override_selling_price'])
            ->withTimestamps();
    }
}
