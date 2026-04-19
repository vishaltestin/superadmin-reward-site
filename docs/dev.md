# 🛠️ Developer Guidelines & Project Handoff

**Official Documentation: UI Standards, API Strategy, and Roadmap**

---

## 1. Filament v3 UI Standards (The Cheat Sheet)
To maintain consistency across the admin panel, all future Filament development must adhere to these v3-specific rules:

* **Strict Namespace Separation:** * Use `Filament\Actions\Action` (not `Tables\Actions`).
  * Keep Tables, Forms, and Pages strictly separated into their respective classes/folders.
* **Immutability by Design:** * For sensitive ledgers (like `TransactionResource`), globally disable `canCreate`, and remove `EditAction` / `DeleteAction`.
* **Action-Driven UI:**
  * Avoid manual input fields for critical data (like balances). Use `Action::make()->form()` to create modals that execute backend logic (e.g., the `manage_funds` action).
* **Schema Layout Rules:**
  * Use `Section` for card-based grouping.
  * Use `Grid` for responsive column layouts.
  * Use `Tabs` for multi-step or complex data entry (e.g., Company Setup).
* **Performance:**
  * Always use eager loading in `getEloquentQuery()` (e.g., `->with(['wallet'])`) to prevent N+1 issues in tables.

---

## 2. Backend-to-Frontend Strategy (React + Laravel)
As we prepare to build the React storefront, here are the architectural rules for the API:

* **Authentication:** Laravel Sanctum will handle API authentication for the React frontend.
* **The Payload Rule (JSON):** * When sending user data to the frontend, the `vertical_data` JSON column from `rewardee_profiles` should be flattened or parsed so React components (using Zod/React Hook Form) can easily read it.
* **Checkout Flow (Future):**
  * When a user checks out on React, the API must trigger the `$user->wallet->debit()` method. The `reference` polymorphic relation should point to an `Order` model.

---

## 3. The Roadmap (Where to start next)

When you open a new chat to continue development, you can paste this exact checklist to pick up right where we left off:

### Phase 1: The Event Engine (Next Immediate Step)
- [ ] Create the `events` table migration (Triggers like Birthdays, Anniversaries).
- [ ] Build the `Campaign` or `Distribution` logic (How a company actually sends points to a user based on an event).
- [ ] Create the Filament UI for Sub-Admins to trigger these campaigns.

### Phase 2: The Storefront API
- [ ] Build the `products` and `orders` tables.
- [ ] Create the API endpoints for the React frontend to fetch the catalog.
- [ ] Create the Checkout API that deducts points from the user's wallet using our FIFO logic.

### Phase 3: Reporting & Compliance
- [ ] Build the custom Filament page for the HR "Perquisite" Tax Report.
- [ ] Build the Super Admin Revenue Dashboard (summing up `fiat_paid`).
