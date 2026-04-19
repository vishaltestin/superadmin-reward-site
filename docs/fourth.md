Here is the official, updated v3 documentation for your platform. You can copy and paste this exactly into your project notes or use it to seamlessly continue development in a new session.

***

# 🚀 State of the App: Corporate Rewarding Platform (Documentation v3)

**Current Build Status: The E-Commerce Catalog & Financial Engine**

---

## 1. Project Overview
A B2B2C SaaS platform enabling companies to onboard users across distinct business verticals, assign digital budgets (via an immutable ledger), and allow users to redeem points or pay cash for global catalog items (Physical Goods, Digital Vouchers, and Experiences).

**Tech Stack:**
* **Backend:** Laravel 13.5 (PHP 8.3)
* **Admin Panel:** Filament PHP v5 (Strict Modular Architecture: Schemas/Tables/Pages separated)
* **Frontend (Upcoming):** React (Zod, React Hook Form, TanStack Query)

---

## 2. Core Architectural Rules (The "Commandments")

1. **The "Points + Pay" Doctrine:** To provide a premium e-commerce experience, catalog items are priced in **Real Fiat (₹)** (e.g., `mrp`, `selling_price`), NOT points. At checkout, 1 Point = ₹1. Users can partially pay with points and cover the rest via a payment gateway.
2. **The "State Path" JSON Architecture:** To prevent database bloat from heterogeneous products (e.g., Travel packages vs. Digital Vouchers), unique fields (`lat/lng`, `departure_date`, `coupon_code`) are stored in a single `type_data` JSON column. The Filament UI dynamically morphs based on the selected Product Type to collect this data cleanly.
3. **Immutable Financial Ledger:** Wallets cannot be updated manually. All balance changes require a `Transaction` record. Points have an `expires_at` date and are consumed using FIFO (First-In, First-Out) logic.
4. **Primary + Secondary Taxonomy:** A product belongs to exactly ONE Primary Category (for SEO and main breadcrumbs) but can belong to INFINITE Secondary Categories via a pivot table (for dynamic tagging like "Summer Sale").
5. **Strict Multi-Tenancy & Scoping:** All user entities are scoped by `company_id`. Sub-admins are restricted via `admin_vertical_access` (e.g., HR can only manage "Internal Employees").

---

## 3. Database Schema & Models (THE E-COMMERCE LAYER)

### Core Catalog
* **`brands`**: `name`, `slug`, `logo`, `is_active`.
* **`products`**: The master item.
  * *Identity:* `type` (physical/digital/experience), `name`, `slug`, `sku`, `brand_id`, `category_id` (Primary).
  * *Pricing & Compliance:* `mrp`, `selling_price`, `gst_percentage`, `warranty_info`.
  * *Content:* `short_description`, `long_description`, `key_features` (JSON), `terms_and_conditions`.
  * *Media & Sort:* `main_image`, `gallery_images` (JSON), `video_url`, `sort_order`.
  * *Flexible Data:* `specifications` (JSON), `tags` (JSON), `type_data` (JSON - houses custom fields for vouchers/travel).

### Catalog Engines (Sub-Tables)
* **`product_variants`**: The actual physical variations on the shelf. `product_id`, `name` (e.g., "Red - XL"), `sku`, `selling_price` (variant override), `stock_quantity`, `attributes` (JSON - e.g., `{"Size": "XL", "Color": "Red"}`).
* **`product_tier_prices`**: The B2B Bulk Discount engine. `product_id`, `min_quantity` (e.g., 50), `selling_price` (e.g., ₹400).
* **`category_product`**: Pivot table for Secondary Categories/Collections.

### The Exception Engine (B2B White-Labeling)
* **`company_product`**: Pivot table allowing Super Admins to alter the global catalog for specific companies.
  * *Fields:* `is_excluded` (Hide item), `override_name` (e.g., "Ford Branded Mug"), `override_image`, `override_mrp`, `override_selling_price`.

---

## 4. Filament Admin Modules (v3 Standards)

* **`ProductResource`:** The crown jewel. Uses a 4-Tab `ProductForm` (Core Details, Taxonomy, Content & Specs, Media). Dynamically shows/hides Voucher or Travel fields based on the selected `type` using `$get('type')`.
  * **Action:** Features a custom `ReplicateAction` on the table to 1-click duplicate products (appends "(Copy)" and clears unique SKUs).
  * **Relation Managers:**
    1.  `VariantsRelationManager`: Inline CRUD for sizes/colors.
    2.  `TierPricesRelationManager`: Inline CRUD for bulk B2B discounts.
    3.  `CustomCompaniesRelationManager`: An `AttachAction` table allowing admins to select a company and apply Exception Rules (exclusions, overrides).

---

## 5. Next Roadmap Steps (Phase 4: The E-Commerce Loop)

*When starting the next development sprint, begin with these specific modules to bridge the Catalog to the User Checkout.*

### Step 1: The Digital Inventory (Code Vault)
* **Goal:** Store the actual redeemable codes for Digital Vouchers.
* **Task:** Build a `voucher_codes` table (`product_id`, `code`, `pin`, `is_used`, `issued_to_user_id`, `issued_at`). Build a Filament Relation Manager to allow admins to bulk-import 500 Amazon codes via CSV into a specific Product.

### Step 2: The Order & Checkout Engine
* **Goal:** Record purchases and execute the "Points + Pay" math.
* **Task:** Build `orders` table (`user_id`, `company_id`, `subtotal`, `gst_total`, `points_used`, `fiat_paid_via_gateway`, `status`). Build `order_items` table.
* **Logic:** Tie the Order creation to the existing `Wallet->debit()` FIFO logic.

### Step 3: Logistics (Addresses & Shipping)
* **Goal:** Collect delivery data for `physical` product types.
* **Task:** Build a `user_addresses` table. Ensure the Order engine correctly captures the selected address snapshot at the time of checkout.

### Step 4: The React Storefront API (Sanctum)
* **Goal:** Expose the data for the frontend.
* **Task:** Build controllers that fetch products. Ensure the API applies the `company_product` Exception Engine rules *before* sending the payload to the user (hiding excluded items and overriding names/prices based on their logged-in `company_id`).