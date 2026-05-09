Here is your **Master TODO & Implementation Checklist**. I have extracted every placeholder, missing logic block, and pending frontend task we discussed so you have a complete roadmap for the rest of this module. 

You can copy this into your project management tool (Notion, Jira, GitHub Issues) to track our progress.

---

# 📋 Campaign Management Module: Master Implementation Checklist

## Phase 1: The React Frontend (The 13-Step Wizard)
These are the UI components and state management pieces we need to build next.

### State Management
*   [ ] **Create `useCampaignBuilderStore` (Zustand):**
    *   Track `currentStep` and `furthestStepReached`.
    *   Hold draft state for all 13 steps.
    *   Create computed properties (e.g., `estimatedTotalCost` = recipients × reward value).
*   [ ] **Implement Zod Validation Schemas:** Create step-by-step schemas to prevent advancing if a step is invalid.

### The UI Shell & Routing
*   [ ] **Build the Wizard Layout:** A sticky header with step indicators and a "Next/Back" footer.
*   [ ] **Implement Step 3 Branching:** Logic to route the UI to Flow A (Online) or Flow B (Bulk) based on `distribution_type`.

### Step-Specific UI Components
*   [ ] **Step 4A (Recipients Table):** Re-use the existing employee table but add multi-select checkboxes.
*   [ ] **Step 4A (Bulk Upload):** Wire up the CSV uploader to hit an API, validate emails, and push valid `user_id`s into the Zustand store.
*   [ ] **Step 5A (Wallet Check Widget):** Compare computed cost vs. company wallet balance. Show an inline "Add Funds" prompt if insufficient.
*   [ ] **Step 5B & 6B (Bulk Variant Matrix):** Build the wholesale grid where admins type explicit quantities for product variants (S, M, L / Red, Blue).
*   [ ] **Step 13 (Summary):** Build read-only review cards with "Edit" buttons that rewind the wizard state.

---

## Phase 2: Backend Pending Tasks & Placeholders
These are the specific logic blocks inside the Laravel backend that currently have `// TODO` comments or require 3rd-party integration.

### Queue Jobs
*   [ ] **Implement `ProcessBulkCampaignOrderJob`:** 
    *   *Location:* `CampaignController@store` (Line 84).
    *   *Task:* If `distribution_type` is `bulk`, write the job that loops through the Variant Matrix from `config_json` and creates the physical `Order` and `OrderItem` records instantly.
*   [ ] **Implement Actual Mail/SMS Sending:** 
    *   *Location:* `DispatchCampaignCommsJob@handle` (Line 46).
    *   *Task:* Replace the commented-out `Mail::html()` block with your actual Mail provider (AWS SES, Resend, Mailgun) and add the SMS provider logic (Twilio, MSG91).

### Controllers & Business Logic
*   [ ] **Enforce Catalog Restrictions on Claims:**
    *   *Location:* `ClaimController@executeClaim` (Line 59).
    *   *Task:* Add the `if()` statement that checks if the selected `$variant->product->category_id` is inside the `$entitlement->campaign->config_json['allowed_category_ids']`. Abort if the user tries to claim an unauthorized item.
*   [ ] **Direct Points Deposit Logic:**
    *   *Location:* `routes/console.php` (Inside `campaign:activate-scheduled`).
    *   *Task:* If a scheduled campaign is `reward_type === 'points'`, write the loop that skips generating links/emails and directly calls `$entitlement->user->wallet()->credit()`.

---

## Phase 3: The Employee Claiming Portal
The public-facing frontend where employees redeem their gifts.

*   [ ] **Build the Magic Link Route:** `GET /claim?token=xyz`.
*   [ ] **Implement `useClaimTokenQuery`:** Fetch the validation data from the backend to ensure the link isn't expired or claimed.
*   [ ] **Render the Dynamic Landing Page:** Feed the `campaign_config['landing_page_json']` into a read-only GrapesJS viewer or a React renderer so the employee sees the custom design.
*   [ ] **Build the Single Page Checkout Drawer:**
    *   A slide-out form for shipping details.
    *   A variant selector (if the gift requires a size/color).
*   [ ] **Integrate the "Pay + Points" Upsell:**
    *   If the selected gift costs more than the `reward_value`, mount the DodoPayments/Razorpay widget to collect the `fiat_paid` difference before calling `/execute`.

---

Whenever you are ready to start knocking these out, let me know if you want to begin with **Phase 1 (The Zustand Store & React Wizard)** or if you want to clear out the remaining **Phase 2 (Backend Jobs)**!