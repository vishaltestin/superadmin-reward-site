Here is the official documentation specifically dedicated to the **Digital Voucher Engine** and the **Code Vault**, detailing exactly how it functions within your Corporate Rewarding Platform.

***

# 🎟️ Digital Voucher Engine & Code Vault (Documentation)

## 1. Module Overview
The Digital Voucher Engine handles non-physical products (e.g., Amazon Gift Cards, 50% Off Electronics Coupons) that require instant digital delivery. 

Unlike physical products that track aggregate `stock_quantity`, digital vouchers require a **Code Vault**—a strict 1-to-Many relational database structure that stores unique, single-use alphanumeric codes. When a user purchases a voucher, the system securely extracts one unused code from the vault and permanently assigns it to that user.

---

## 2. Database Architecture

The architecture relies on two tables working in tandem: the polymorphic `products` table and the highly specific `voucher_codes` table.

### A. The Parent Product (`products` table)
The master record defining the voucher.
* **Core Identity:** `type` is strictly set to `'digital'`.
* **State Path Storage (`type_data` JSON):** To prevent database bloat, all voucher-specific metadata is stored cleanly in a JSON column.
    * `couponCode`: A universal display code (if applicable).
    * `validUntil`: General promotion expiry.
    * `redemptionLink`: URL where the user claims the offer.
    * `storeName`, `phone`, `website`, `address`, `pincode`: Merchant details.
    * `mapLocation_lat`, `mapLocation_lng`: Geolocation for map rendering.
    * `backgroundColor`: Hex code for frontend UI rendering.
    * `aboutBrand`: HTML rich text about the merchant.

### B. The Code Vault (`voucher_codes` table)
The digital inventory. Every row represents a single, purchasable instance of the parent voucher.
* `product_id`: Foreign key to the parent product.
* `code`: The unique alphanumeric string (e.g., `AMZN-8X92-KJ41`). Must be unique across the database.
* `pin`: Optional secondary security PIN.
* `is_used`: Boolean (Default: `false`). Locks to `true` upon purchase.
* `issued_to_user_id`: Foreign key to the `users` table. Null until purchased.
* `issued_at`: Timestamp of when the checkout occurred.
* `expires_at`: Optional hard expiry for the specific code.

---

## 3. Admin UX & Filament Integration

The Super Admin experience is heavily optimized using Filament's reactive forms to ensure data integrity.

### A. Dynamic Form Morphing
In the `ProductResource` Create/Edit form, selecting "Digital Voucher" from the core type dropdown triggers reactive UI updates:
1. **Hides Irrelevant Fields:** Shipping dimensions, weights, and travel departure dates disappear.
2. **Shows the Voucher Tab:** A "Specific Details" tab appears, presenting fields for geocoordinates, redemption links, and background colors. These inputs automatically map to the `type_data` JSON array.

### B. The Vault UI (`VoucherCodesRelationManager`)
A dedicated table sitting at the bottom of the Product Edit page.
* **Visibility Constraint:** Uses `canViewForRecord` to ensure this table *only* renders if the product `type` is `'digital'`.
* **Features:**
    * Displays all codes, their usage status (Red/Green indicators), and who claimed them.
    * Admins can Create single codes manually.
    * *Security:* The `EditAction` and `DeleteAction` are explicitly hidden for any row where `is_used` is true, preventing admins from altering a code that a user has already paid for.

---

## 4. The Business Lifecycle (How it Works)

1. **Creation:** Super Admin creates a new Product, sets type to "Digital Voucher", and fills in the `type_data` (e.g., redemption link, store name).
2. **Stocking the Vault:** Super Admin scrolls to the Voucher Codes table and adds 100 unique codes provided by the merchant. All 100 codes start as `is_used = false`.
3. **Frontend Display:** The React API queries the product. It checks the `voucher_codes` table for `count()` where `is_used = false` to determine if the item is "In Stock".
4. **Checkout & Claim (The Transaction):**
    * User completes checkout using Points/Fiat.
    * The Backend queries the vault: `VoucherCode::where('product_id', $id)->where('is_used', false)->firstLockForUpdate()`.
    * The Backend updates that specific code: `is_used = true`, `issued_to_user_id = $userId`, `issued_at = now()`.
5. **Delivery:** The API returns the exact `code` string in the Order Confirmation payload, allowing the React frontend to display it to the user.