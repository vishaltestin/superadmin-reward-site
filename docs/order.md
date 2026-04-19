Here is the official documentation for the **Order & Checkout Engine**, continuing the standard set by the previous modules. You can add this directly to your project documentation.

***

# 🛒 Order & Checkout Engine (Documentation)

## 1. Module Overview
The Order & Checkout Engine is the central nervous system of the platform's e-commerce loop. It acts as the bridge between the **Catalog** (what is being sold) and the **User Wallet** (how it is being paid for).

Unlike standard e-commerce platforms, this engine is designed around the **"Points + Pay" Doctrine**, allowing corporate users to partially fund a transaction using their reward points and pay the remaining balance via a fiat payment gateway (e.g., Razorpay/Stripe).

**Core Philosophy: Immutability.** An order is a strict financial receipt. Once generated, the prices, names, and totals are permanently snapshotted. If a product’s price changes in the catalog tomorrow, historical orders remain unaffected.

---

## 2. Database Architecture

The architecture uses a classic Header/Line-Item structure, heavily optimized for financial auditing and B2B tenancy.

### A. The Master Receipt (`orders` table)
This table stores the aggregate financial math and lifecycle state of the checkout.
* **Tenancy & Ownership:** `company_id` and `user_id` strictly bind the order to a corporate entity and a specific employee.
* **The Financial Ledger:**
    * `total_amount`: The full cart value (including GST).
    * `gst_total`: The total tax collected across all items.
    * `points_used`: Integer representing how much of their digital wallet balance was burned.
    * `fiat_paid`: Decimal representing the cash balance processed via credit card.
* **Tracking & State:**
    * `order_number`: Auto-generated unique alphanumeric string (e.g., `ORD-2026-X9B2`).
    * `payment_gateway_reference`: Stores the external transaction ID.
    * `status`: Enum tracking the lifecycle (`pending`, `paid`, `processing`, `shipped`, `completed`, `cancelled`, `failed`).

### B. The Line Items (`order_items` table)
This table breaks down the exact contents of the cart at the exact millisecond of purchase.
* **Relationships:** Links to `order_id`, `product_id`, and `product_variant_id` (if a specific size/color was chosen).
* **The Immutable Snapshot:**
    * `product_name`: Copied from the product table. Ensures the receipt remains legible even if the original product is soft-deleted years later.
    * `unit_price`: The specific price the user paid at checkout.
    * `unit_gst_percentage`: The tax bracket applied to this specific item.
    * `total_price`: `quantity` × `unit_price`.
* **Logistics:** `delivery_status` tracks items individually (e.g., a digital voucher is `delivered` instantly, while the physical mug in the same cart is `pending` shipment).

---

## 3. Admin UX & Filament Integration (`OrderResource`)

The Admin Panel UI for Orders is fundamentally different from Products. It is designed for **Auditing and Processing**, not content creation.

* **The Read-Only Principle:** Super Admins cannot create orders manually, nor can they edit prices, items, or totals. This prevents accidental corruption of the financial ledger.
* **Layout Design:**
    * **Left Column (2/3 width):** Order Management. Displays the Order Number, Tenancy details (Company/User), and allows admins to update the `status` dropdown as the item moves through the warehouse.
    * **Right Column (1/3 width):** The Financial Ledger. A clean, color-coded breakdown of Total Cost, GST, Points Deducted, and Fiat Paid.
* **The Cart Viewer (`ItemsRelationManager`):** A read-only table at the bottom of the page showing exactly what was bought, including variant details (e.g., "Apple AirPods - Standard Edition") and individual line-item totals.

---

## 4. The Business Lifecycle (How it Works)

1. **Cart Construction (Frontend):** The React API compiles the user's cart, fetching current prices from the `products` and `product_variants` tables.
2. **The Math Verification:** The backend calculates the total. It debits the maximum available points from the user's `Wallet` (creating a Wallet Transaction) and pushes the remaining balance to the Payment Gateway.
3. **Order Generation:** Upon gateway success, the backend creates the `Order` record and iterates through the cart to create `OrderItem` records, snapshotting all prices.
4. **Fulfillment Routing:**
    * **If Digital:** The backend immediately hits the `voucher_codes` table, assigns a code to the user, and marks the `order_item` as `delivered`.
    * **If Physical:** The Order status is set to `processing`. The warehouse team checks the Filament dashboard, packs the item, and changes the status to `shipped`.

---

## 5. Next Steps / Dependencies
To complete the physical delivery loop, the Order Engine requires the **Logistics Module** (`user_addresses` table) so the system can snapshot *where* the user requested the items to be shipped at the time of checkout.