# Technical Documentation: Variant-Aware B2B Catalog Overrides & Custom Tier Pricing Engine

This document outlines the architecture, data layers, and fallback compilation rules designed and deployed for the Variant-Aware Exception Engine and Negotiated Bulk Volume Rate System.

---

## 1. Relational Database Layout (The Exception Engine Layers)

To prevent structural collisions between variant properties and tenant overrides, the application isolates B2B data anomalies into two dedicated tracking layouts.

### 1.1 `company_product_variant` (Atomic Configuration Overrides)

This pivot layout overrides single item parameter properties (e.g., Matte Black / 1 Liter configuration) for a targeted corporate tenant.

| Field Name | Data Type | Key Type | Operational Constraints / Function |
| --- | --- | --- | --- |
| `id` | BigInt | PK | Auto-incrementing row reference index. |
| `company_id` | BigInt | FK | Points to `companies.id` (Cascades on parent delete). |
| `product_variant_id` | BigInt | FK | Points to `product_variants.id` (Cascades on parent delete). |
| `override_image` | String | None | Custom workspace asset file path (Nullable). |
| `override_mrp` | Decimal (15,2) | None | Custom retail baseline currency comparison limit (Nullable). |
| `override_selling_price` | Decimal (15,2) | None | Custom transactional cost mapped to this tenant account (Nullable). |
| `created_at` / `updated_at` | Timestamp | None | Operational timeline execution hooks. |

* **Unique Composite Key Constraint:** `['company_id', 'product_variant_id']` named `company_variant_unique`. This prevents conflicting dataset lines from being mapped to the same tenant configuration.

### 1.2 `company_product_tier_prices` (Negotiated Bulk Rate Sheets)

This independent model stores scaled contract milestone pricing rules per corporate client context.

| Field Name | Data Type | Key Type | Operational Constraints / Function |
| --- | --- | --- | --- |
| `id` | BigInt | PK | Auto-incrementing row reference index. |
| `company_id` | BigInt | FK | Points to `companies.id` (Cascades on parent delete). |
| `product_id` | BigInt | FK | Points to `products.id` (Cascades on parent delete). |
| `product_variant_id` | BigInt | FK | Points to `product_variants.id` (Nullable, Cascades on delete). |
| `min_quantity` | Integer | None | The quantitative breakpoint volume required to unlock the row rate. |
| `selling_price` | Decimal (15,2) | None | Contract-negotiated per-unit cost applied at this threshold. |

* **Unique Composite Key Constraint:** `['company_id', 'product_id', 'product_variant_id', 'min_quantity']` named `company_tier_prices_unique`. This enforces unambiguous threshold mapping lines.
* **Structural Flexibility Flag:** If `product_variant_id` is stored as `NULL`, the volume step applies broad product-wide threshold rates to the client across any item configuration in that product family.

---

## 2. Eloquent Model Bindings

The active domain data objects manage the many-to-many lookup trees using explicitly declared relationship methods.

### 2.1 Modifications to `App\Models\Company.php`

Allows corporate accounts to reach out and fetch their assigned variant modifications:

```php
public function variantOverrides(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(ProductVariant::class, 'company_product_variant')
        ->withPivot(['override_image', 'override_mrp', 'override_selling_price'])
        ->withTimestamps();
}

```

### 2.2 Modifications to `App\Models\ProductVariant.php`

Allows variant objects to locate tenant configurations inside the data map:

```php
public function companyOverrides(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(Company::class, 'company_product_variant')
        ->withPivot(['override_image', 'override_mrp', 'override_selling_price'])
        ->withTimestamps();
}

```

---

## 3. Filament Admin Dashboard Processing Layout

The interface layer inside `CustomCompaniesRelationManager.php` uses raw data operations and state hooks to process file assets and multi-dimensional pricing fields without colliding with the database schema.

### 3.1 Hydration Mechanics (`afterStateHydrated`)

Because standard file system components in Filament demand array properties to track upload sessions, passing a raw string path straight from the database causes an operational error. The hydration loop processes database properties into the correct formats when loading the edit modal:

```php
->afterStateHydrated(function (Repeater $component, ?Model $record) {
    if (! $record) return;
    
    // Target the variants belonging strictly to the current parent product context
    $productVariantIds = $this->getOwnerRecord()->variants()->pluck('id')->toArray();

    $currentOverrides = DB::table('company_product_variant')
        ->where('company_id', $record->id)
        ->whereIn('product_variant_id', $productVariantIds)
        ->get()
        ->map(fn ($item) => [
            'product_variant_id'     => $item->product_variant_id,
            'override_selling_price' => $item->override_selling_price,
            // SANITIZATION: String values are wrapped into an array list to satisfy Filament
            'override_image'         => $item->override_image ? [$item->override_image] : [],
        ])->toArray();

    $component->state($currentOverrides);
})

```

### 3.2 Dehydration & Save Mechanics (`saveRelationshipsUsing`)

When an operator saves the form, data components drop files as an array string block. The save lifecycle manually processes values before committing changes to MySQL:

```php
->saveRelationshipsUsing(function (?Model $record, array $state) {
    if (! $record) return;

    $productVariantIds = $this->getOwnerRecord()->variants()->pluck('id')->toArray();

    // Purge existing rules within this product scope to clear out disabled selections
    DB::table('company_product_variant')
        ->where('company_id', $record->id)
        ->whereIn('product_variant_id', $productVariantIds)
        ->delete();

    foreach ($state as $row) {
        if (empty($row['product_variant_id'])) continue;

        // SANITIZATION: Extract the string out of Filament's file upload container array
        $overrideImage = null;
        if (! empty($row['override_image'])) {
            $overrideImage = is_array($row['override_image']) 
                ? (current($row['override_image']) ?: array_key_first($row['override_image'])) 
                : $row['override_image'];
        }

        DB::table('company_product_variant')->insert([
            'company_id'             => $record->id,
            'product_variant_id'     => $row['product_variant_id'],
            'override_selling_price' => $row['override_selling_price'] ?: null,
            'override_image'         => $overrideImage, // Safe scalar string passed
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }
})

```

---

## 4. Storefront API Layer Price & Image Fallback Matrices

When a user opens an item, the `StorefrontCatalogController.php` processes the dynamic properties using an explicit cascading fallback pattern.

### 4.1 Resolving Empty Form Input Fields

Because standard dashboard input forms pass omitted text fields or deleted image selections as empty strings (`""`) rather than clean SQL `NULL` markers, the standard null coalescing operator (`??`) will fail by resolving the blank string. The backend instead runs strict checks using `!empty()` and `is_numeric()`.

### 4.2 Core Fallback Priority Cascades

The backend loops through each variant attached to the product schema, executing resolution lookups in this exact sequence:

```
[Variant Evaluated] ───► !empty(Variant Override Name/Image/Price)? ───► YES ───► Apply Tenant Variant Value
                             │
                             ▼ NO
                         !empty(Global Variant Default)? ───────────────► YES ───► Apply Global Variant Default
                             │
                             ▼ NO
                         !empty(Tenant Base Product Override)? ─────────► YES ───► Apply Tenant Product Override
                             │
                             ▼ NO
                         Load Global Master Product Default Baseline ────────────────► Apply Master Product Baseline

```

### 4.3 Hybrid Context-Aware Volume Tier Pricing Integration

To prevent a client's custom tier configuration on one specific item option from accidentally wiping out standard volume discount tables for other configurations, the engine performs split compilation lookups:

```php
$globalTiers = $product->tierPrices;
$companyTiers = CompanyProductTierPrice::where('company_id', $company->id)
    ->where('product_id', $product->id)
    ->get();

$resolvedTiers = collect();

// 1. Establish the Product-Wide Base Tier Lines
$baseCompanyTiers = $companyTiers->whereNull('product_variant_id');
if ($baseCompanyTiers->isNotEmpty()) {
    $resolvedTiers = $resolvedTiers->concat($baseCompanyTiers);
} else {
    $resolvedTiers = $resolvedTiers->concat($globalTiers->whereNull('product_variant_id'));
}

// 2. Loop Variant Configuration Items Independently to Protect Missing Tier Sets
foreach ($product->variants as $variant) {
    $vCompanyTiers = $companyTiers->where('product_variant_id', $variant->id);
    
    if ($vCompanyTiers->isNotEmpty()) {
        // Use custom company negotiated tiers if explicitly typed for this option
        $resolvedTiers = $resolvedTiers->concat($vCompanyTiers);
    } else {
        // Preserve original wholesale thresholds if no blanket corporate layout exists
        if ($baseCompanyTiers->isEmpty()) {
            $resolvedTiers = $resolvedTiers->concat($globalTiers->where('product_variant_id', $variant->id));
        }
    }
}

```

---

## 5. Frontend Storefront Performance & UX Rules

The user interface layer inside `ProductPage.tsx` synchronizes user actions cleanly while processing our contextual fallback backend data blocks.

### 5.1 Removing Layout Flickering

Layout flickering happens when the interface attempts to process multi-threaded React state cycles simultaneously during attribute shifts. The interface eliminates this by isolating states:

* **The Single Source Image Rule:** `displayImage` is managed via a strict `useMemo` computation tracking `activeThumbnail || selectedVariant?.image_url || product?.main_image_url`.
* **State Decoupling:** Inside `handleAttributeSelect`, the component forces `setActiveThumbnail(null)` the instant an option is clicked. This clears out temporary thumbnail selections and lets the layout render the correct, tenant-aware variant image calculated by the backend API.

### 5.2 Predictive Option Styling

To help shoppers navigate valid option pairs, the layout scans available variants in real time before rendering selection buttons. If a button parameter cannot be paired with the user's *currently selected options*, the application styles it with a lower opacity and a dashed border. This tells the shopper it's an alternate combination before they click it, providing a smooth, intuitive user experience.

```tsx
const isValidCombination = product.variants.some((v) => {
  if (v.attributes[attrKey] !== val) return false
  return Object.entries(activeAttributes).every(([k, currentVal]) => {
    if (k === attrKey) return true // Skip evaluation for the active row index
    return v.attributes[k] === currentVal
  })
})

```