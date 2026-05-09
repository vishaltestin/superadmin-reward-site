
---

# 📘 Master Specification: Campaign Management Module

## 1. Executive Summary
The Campaign Builder is the core financial and distribution engine of the platform. It allows Company Administrators (Business Heads & Sub-Admins) to allocate company budgets, distribute digital rewards (Points, Promo Codes, Magic Links) to employees, or execute large-scale B2B physical bulk orders. It is designed to handle high-volume distributions (e.g., 10,000+ employees) asynchronously without blocking the UI or overloading the server.

---

## 2. Database Architecture (The Data Layer)
Two new primary tables will be introduced to handle campaigns without polluting the core e-commerce storefront.

### A. `campaigns` Table (The Parent Record)
Acts as the financial escrow and configuration holder.
*   **Identity:** `id`, `company_id`, `created_by_user_id`, `name`, `description`.
*   **Targeting:** `vertical_id`, `event_id`, `total_recipients`.
*   **Branching Enablers:** 
    *   `distribution_type`: `online` or `bulk`.
    *   `reward_type`: `points`, `code`, `link`, or `physical_bulk`.
*   **The Escrow Lock:** `budget_locked` (decimal) - Holds the deducted fiat/points until the campaign is completed or expired.
*   **Configuration:** `config_json` - Stores catalog restrictions (e.g., specific category IDs), selected email/landing page templates, SMS copy, and variant matrices for bulk orders.
*   **Lifecycle:** `status` (`draft`, `processing`, `scheduled`, `active`, `completed`, `cancelled`), `starts_at`, `expires_at`.

### B. `campaign_entitlements` Table (The Deposit Slips)
Used **only** for Online Distribution (`points`, `code`, `link`). Acts as a secure, individual claim ticket for each recipient.
*   **Identity Lock:** `id`, `campaign_id`, `issued_to_user_id` (Strictly limits claiming to the intended employee).
*   **Value:** `reward_value` (decimal).
*   **Delivery Mechanisms:**
    *   `claim_token`: Unique 64-char string for 1-Click Magic Links.
    *   `claim_code`: Human-readable string for Promo Codes (e.g., `DIWALI-2026-X9B2`).
*   **Status & Expiry:** `is_claimed` (boolean), `claimed_at`, `expires_at`, `reminded_at`.

---

## 3. Frontend Architecture (React / Client Layer)

### State Management (`useCampaignBuilderStore`)
A robust Zustand store will handle the 13-step wizard to ensure data persistence across steps.
*   **Properties:** Tracks `currentStep`, `furthestStepReached`, `selectedRecipients` (array of user IDs), and computed properties like `estimatedTotalCost`.
*   **Validation:** Uses Zod schemas tightly coupled with `react-hook-form` on a *per-step* basis. Users cannot advance unless the current step is strictly valid.

### Branching Wizard
The UI dynamically renders different steps based on Step 3 (`distribution_type`).

---

## 4. The 13-Step Wizard Flow (Admin Experience)

*   **Step 0: Campaign Details** - Name (Required) and Description.
*   **Step 1: Select Vertical** - Determines the audience (RBAC enforced: Sub-admins only see verticals they manage).
*   **Step 2: Select Event** - Determines the occasion (fetches event triggers based on the selected vertical).

🛑 **Step 3: Distribution Type (The Branching Point)**
Admin selects **Online Gift Distribution** or **Bulk Order**.

### 🔀 FLOW A: Online Gift Distribution
*   **Step 4A: Select Recipients** - Admins use an interactive data table to select users. Includes a **Bulk Upload CSV** tool (hits an API to validate emails and appends valid `user_id`s to the Zustand store).
*   **Step 5A: Reward Type & Wallet Check** - Admin selects Points, Code, or Link. Enters value per user. System calculates `Total Cost` and checks against the Company Wallet. Includes an inline "Add Funds" widget if the balance is low.
*   **Step 6A & 7A: Catalog Selection (Freedom vs. Restriction)**
    *   *Default Catalog:* Open freedom. Reward acts as liquid cash.
    *   *Custom Catalog:* Admin locks the reward to specific Categories or Products (saved to `config_json`).
*   **Step 8A: Landing Page Template** - Admin selects the post-click web experience.
*   **Step 9A: Email Template** - Admin selects the delivery email design.
*   **Step 10A: SMS Configuration** - Admin drafts the text message alert.
*   **Step 11A: Schedule & Reminders** - Admin sets `starts_at` and `expires_at`, plus automated reminder toggles (Hidden if reward type is "Points").

### 🔀 FLOW B: Bulk Order (Physical Delivery)
*   **Step 4B: Bulk Logistics** - Admin enters physical Event Address, Date, and Target Headcount.
*   **Step 5B & 6B: Wholesale Catalog & Variant Matrix** - Admin browses the store, selects items, and inputs explicit quantities into a size/color matrix (e.g., 50 Small, 100 Medium). Applies wholesale volume discounts dynamically. *(Steps 8-11 are skipped).*

🏁 **Step 13: Summary & Submission (Both Flows)**
Read-only review cards with "Edit" buttons to jump back. Clicking "Submit" triggers the backend engine.

---

## 5. Backend Engineering (The Asynchronous Engine)

To prevent HTTP timeouts when processing 10,000+ employees, the backend utilizes strict Queueing and Escrow logic.

### The Submission API
1. Creates the `Campaign` record with `status = processing`.
2. **The Escrow:** Debits the `Company->wallet` for the total amount and saves it to `campaigns.budget_locked`.
3. Dispatches `ProcessCampaignJob` and returns `200 OK` to the frontend.

### Background Queue Jobs
*   **`ProcessCampaignJob`:** Chunks the `selectedRecipients` array. Generates `campaign_entitlements` rows, creating the cryptographic `claim_token`s and `claim_code`s. Once finished, updates status to `active` (or `scheduled`).
*   **`DispatchCampaignCommsJob`:** Triggered when a campaign goes active. Handles the heavy lifting of sending thousands of SES Emails and Twilio SMS messages.

### Cron Schedulers (Task Scheduling)
*   `campaign:activate-scheduled` (Daily): Finds campaigns where `starts_at` is today. Sets to active and dispatches comms.
*   `campaign:send-reminders` (Daily): Checks for unclaimed entitlements matching the reminder timeframe and dispatches nudge emails.
*   `campaign:clawback-expired` (Daily): Finds unclaimed entitlements past their `expires_at` date. Marks them expired and securely **refunds** the exact value back to the `Company->wallet`.

---

## 6. Reward Mechanics & Employee Claiming Flows

How the three digital reward types function in the real world:

### 1. Value of Points (Liquid)
*   **Execution:** Handled entirely in the background. `ProcessCampaignJob` directly credits the employee's wallet.
*   **Experience:** Employee gets an email: "₹500 has been deposited to your account."

### 2. Value of Code (Deferred & Restricted at Checkout)
*   **Execution:** Employee receives an email with a promo code. 
*   **Experience:** They log into the storefront, add items to their cart, and paste the code.
*   **Restriction Logic:** If the campaign has a "Custom Catalog" lock, the backend intercepts the checkout. If *any* item in the cart violates the allowed categories, the code is rejected with an error.

### 3. Reward Link / Magic Link (Single Page Checkout)
*   **Execution:** Employee clicks the link in their email (`/claim?token=xyz123`).
*   **Experience:** They are routed to a **Single Page Checkout** (rendered using the Landing Page template selected in Step 8A).
*   **Freedom Case:** They see a "Claim to Wallet" button OR a curated list of top gifts.
*   **Restriction Case:** The page *only* renders the 3 or 4 specific gifts the Admin allowed.
*   **The "Pay + Points" Upsell:** They select a gift. A slide-out asks for their shipping address. If the gift exceeds their link's value (e.g., Link = ₹1000, Gift = ₹1500), a payment gateway mounts inline to charge their personal credit card for the ₹500 difference.
*   **Completion:** The backend marks `is_claimed = true`, creates a standard `Order`, and releases the funds from the Campaign's `budget_locked` escrow.

---

### End of Documentation

This blueprint covers the data structures, API behaviors, UI flows, and enterprise scaling protections. 

**If this looks perfect to you, let me know which phase we are tackling first: The React Frontend (Zustand/UI Shell) or the Laravel Backend (Migrations/Controllers/Jobs)!**