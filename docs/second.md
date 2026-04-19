# 🚀 State of the App: Corporate Rewarding Platform (Documentation v2)

**Current Build Status & Complete Architecture Reference**


## 2. Core Architectural Rules (The "Commandments")

1. **The "Points vs. Fiat" Doctrine:** The internal ledger **never** tracks fiat currency as a balance. Balances are strictly digital "Points" (the platform's liability). Fiat money (the platform's revenue) is only recorded as an immutable receipt on the transaction level (`fiat_paid`).
2. **Hybrid JSON Data Strategy:** Core user identity (Name, Email, Mobile, Company ID) lives in the `users` table. All dynamic/custom data specific to a user's industry (e.g., `testDrive`, `property_type`) is isolated in a `vertical_data` JSON column in the `rewardee_profiles` table to prevent EAV bloat.
3. **Strict Multi-Tenancy:** All entities are scoped by `company_id`.
4. **Sub-Admin Scoping:** Sub-admins are restricted via the `admin_vertical_access` pivot table, meaning they can only manage users within their assigned Verticals (e.g., restricted to "Internal Employees" only).
5. **Macro Catalog Filtering:** Products are grouped by `categories`. The `category_company` pivot table acts as a master gatekeeper—companies only see products inside categories they are explicitly granted.
6. **Immutable Financial Ledger:** Wallets cannot be updated manually. All balance changes require a `Transaction` record (credit or debit).

---

## 3. Database Schema & Models

### Core Identity & Access
* **`companies`**
  * *Columns:* `id`, `name`, `number_of_employee`, `gst_no`, `pan_no`, `industry`, `address`, `alias` (storefront URL), `logo`, `points_name`, `point_multiplier` (conversion rate for fiat-to-points), `is_active`, `is_approved`.
  * *Note:* `available_funds` was permanently removed to enforce ledger-based accounting.
* **`users`**
  * *Columns:* `id`, `company_id`, `user_type` (super_admin, business_head, sub_admin, rewardee), `first_name`, `last_name`, `email`, `mobile`, `password`, `is_active`.
* **`rewardee_profiles`**
  * *Columns:* `id`, `user_id`, `company_id`, `vertical_id`, `vertical_data` (JSON).

### Taxonomy & Structure
* **`verticals`**
  * *Columns:* `id`, `name`, `slug`, `description`, `is_active`. (e.g., Internal Employees, Auto Dealers).
* **`categories`** (Catalog taxonomy)
  * *Columns:* `id`, `parent_id`, `name`, `slug`, `image`, SEO fields, `sort_order`, `is_active`.
* **`events`** (Hierarchical gift triggers)
  * *Columns:* `id`, `vertical_id`, `parent_id`, `title`, `icon`, `sort_order`, `is_active`.

### The Financial Engine (Ledger System)
* **`wallets`** (Polymorphic attached to Companies or Users)
  * *Columns:* `id`, `walletable_type`, `walletable_id`, `balance`, `is_active`.
  * *Logic:* Auto-created when a Company is approved or a Rewardee User is registered.
* **`transactions`** (The Immutable Receipt Book)
  * *Columns:* `id`, `wallet_id`, `type` (credit/debit), `amount` (Points transferred), `fiat_paid` (Real revenue collected, nullable), `remaining_amount` (For FIFO expiry), `expires_at` (Timestamp), `reference_type`/`reference_id` (Polymorphic cause), `description`.

### Pivot Tables
* **`company_vertical`**: Which verticals a company has paid for/unlocked.
* **`admin_vertical_access`**: Which verticals a specific Sub-Admin is allowed to manage.
* **`category_company`**: Which product categories a company is allowed to see.

---

## 4. Deep Dive: The Financial Engine Architecture

We have built an enterprise-grade, double-entry polymorphic ledger. 

### A. Polymorphism (The Universal Wallet)
Because of the `walletable` polymorphic relationship, the exact same `wallets` and `transactions` tables serve both **Companies** (holding budgets) and **Users** (holding reward points). 

### B. The Dual-Ledger Concept (Revenue vs. Liability)
When a company pays for points, the transaction captures two distinct values:
1. `amount` (e.g., 60,000): The digital points credited to the wallet. This is the platform's liability.
2. `fiat_paid` (e.g., 50,000.00): The real-world currency deposited into the platform's bank account. This is the platform's revenue.

### C. The Enterprise Multiplier
Companies have a `point_multiplier` (default 1.00). If a VIP client has a 1.20 multiplier, a deposit of ₹50,000 automatically credits 60,000 points. This eliminates manual calculation errors and supports automated payment gateway webhooks.

### D. The FIFO Expiry System (First-In, First-Out)
To prevent infinite financial liability, points expire.
* Credits are stored with an `expires_at` date and a `remaining_amount`.
* When a User spends points (Debit), the system automatically queries the oldest expiring credits and drains their `remaining_amount` first.
* A scheduled Cron Job (`points:expire`) sweeps the database nightly, wiping any credits where `expires_at` is in the past and deducting the expired points from the user's total balance.

---

## 5. Filament Admin Modules (v3+)

*All resources follow a strict v3 modular architecture, separated into `Pages/`, `Schemas/`, and `Tables/`.*

### `CompanyResource`
* **Schema:** Uses `Tabs`, `Grid`, and `Section` components for a clean UI. Fields conditionally calculate values (e.g., Alias auto-generates from Name).
* **Table:** Removed static balance columns. Now displays `wallet.balance`.
* **Custom Action:** `manage_funds`. An interactive modal replacing direct balance edits. It accepts `transaction_type`, `fiat_paid`, and `description`. It auto-calculates the points based on the company's `point_multiplier` via reactive (`live()`) form fields and safely executes the `$wallet->credit()` or `$wallet->debit()` methods.

### `TransactionResource` (The Master Ledger)
* **Rule:** Strictly Read-Only. Global `canCreate`, `EditAction`, and `DeleteAction` are disabled to guarantee audit integrity.
* **Table:** Uses polymorphic relationship loading to display whether the account owner is a `Company` or `User`. Features advanced filtering by Account Type and Transaction Type.

### `UserResource` & `RewardeeResource`
* Manages access and global directories. Forms dynamically show "Managed Verticals" exclusively for Sub-Admins. `RewardeeResource` uses `KeyValue` components to interact cleanly with the JSON `vertical_data` column.

### `EventResource`
* Event engine configurations. Dynamic forms filter the `parent_id` dropdown based on the selected Vertical.

---

## 6. Next Roadmap Steps

1. **The Event Engine Integration:** Wiring the `events` table to actual campaign triggers so Sub-Admins can distribute points from the Company Wallet to User Wallets based on rules.
2. **The Storefront (React Frontend):** Building the catalog UI where users can spend points (which will trigger FIFO debits on their wallets).
3. **Tax & Compliance Reporting:** Building custom Filament pages for HR Sub-Admins to export "Perquisite" tax reports for points issued within a financial year.