# 🚀 State of the App: Corporate Rewarding Platform
**Current Build Status for Context Transfer**

## 1. Project Overview
A B2B2C SaaS platform (similar to Xoxoday) enabling companies to onboard employees, clients, and partners across distinct business verticals, assign budgets, and trigger gift campaigns.

**Tech Stack:**
* **Backend:** Laravel 13.5 (PHP 8.3)
* **Admin Panel:** Filament PHP v5 (Strict Modular Architecture: Schemas/Tables/Pages separated)
* **Frontend:** React (Zod, React Hook Form, TanStack Query)

---

## 2. Core Architectural Rules Established
1. **Hybrid JSON Data Strategy:** Core user identity (Name, Email, Mobile, Company ID) lives in the `users` table. All dynamic/custom data specific to a user's industry (e.g., `testDrive`, `property_type`) is dumped into a `vertical_data` JSON column in the `rewardee_profiles` table.
2. **Strict Multi-Tenancy:** All entities are scoped by `company_id`.
3. **Sub-Admin Scoping:** Sub-admins are restricted via the `admin_vertical_access` pivot table, meaning they can only manage users within their assigned Verticals (e.g., restricted to "Internal Employees" only).
4. **Macro Catalog Filtering:** Products are grouped by `categories`. The `category_company` pivot table acts as a master gatekeeper—companies only see products inside categories they are explicitly granted.

---

## 3. Database Schema & Models (COMPLETED)

### Base Tables
* **`companies`**: `name`, `alias`, `logo`, `points_name`, `is_active`, `is_approved`, `available_funds` (static column currently).
* **`verticals`**: `name`, `slug`, `description` (e.g., Internal Employees, Auto Dealers).
* **`users`**: Base auth. Includes `company_id`, `user_type` (super_admin, business_head, sub_admin, rewardee), `first_name`, `last_name`, `mobile`.
* **`events`**: Hierarchical gift triggers (e.g., Birthdays, Milestones). Includes `vertical_id`, `parent_id` (for sub-grouping), `title`.
* **`categories`**: Catalog taxonomy. Includes `parent_id`, `name`, `slug`, `is_active`.
* **`rewardee_profiles`**: Links User, Company, Vertical. Houses the `vertical_data` (JSON).

### Pivot Tables
* **`company_vertical`**: Which verticals a company has paid for/unlocked.
* **`admin_vertical_access`**: Which verticals a specific Sub-Admin is allowed to manage.
* **`category_company`**: Which product categories a company is allowed to see.

---

## 4. Filament Admin Modules (COMPLETED)
*Folders structured cleanly: `app/Filament/Resources/[ResourceName]/` containing `Pages/`, `Schemas/`, and `Tables/`.*

1. **`UserResource` (Access Management):** * Manages Super Admins, Business Heads, and Sub-Admins.
   * *Logic:* Conditional form fields dynamically show a "Managed Verticals" multiselect only when the `user_type` is set to 'sub_admin'.
2. **`RewardeeResource` (Global Directory):**
   * *Logic:* `->defaultGroup('vertical.name')` creates an accordion-style table grouping all users by vertical.
   * *Logic:* Includes a `KeyValue` form component to cleanly read/edit the JSON `vertical_data` directly from the UI.
3. **`EventResource` (Event Engine):**
   * *Logic:* Dynamic forms where selecting a Vertical strictly filters the `parent_id` dropdown to only show groups belonging to that vertical.



## 6. Future Roadmap (TO BE DEVELOPED)
*(These items are planned but NOT YET coded or migrated)*

### Phase 1: Financial Engine (Ledgers & Wallets)
* **Goal:** Replace static `available_funds` with an immutable ledger for accurate accounting.
* **Plan:** Build a polymorphic `wallets` table (`walletable_id`, `walletable_type`) attached to Companies, Users, and Escrows. Build a `transactions` table for double-entry bookkeeping (credits/debits). 
* **Support:** Must support "Points + Pay" (users combining platform points with real credit card payments at checkout).

### Phase 2: Product & Exception Engine
* **Goal:** Build the global catalog and allow company-specific overrides.
* **Plan:** Build `products` table. Build `company_product` pivot table acting as the "Exception Engine" (to exclude specific products or override standard images/points with branded company swag).

