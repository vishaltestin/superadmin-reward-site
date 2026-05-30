Awesome! Error #1 is officially behind us. Your variant tier pricing will now behave exactly like it's supposed to.

Let's knock down the next major architectural trap.

---

## Error #2: The Double-Accounting Ledger Trap

### The Problem

If we look at your migrations, your `companies` table creates a field called `available_funds`. But shortly after, you introduced a dynamic, polymorphic `wallets` table designed to handle balances (`balance`) and keep clean paper trails via FIFO transactions for *both* users and companies.

Right now, you have **two separate database columns tracking the exact same financial balance for a company**. If one part of your app updates `available_funds` directly, and another triggers your wallet balance ledger system (`$company->wallet()->credit(...)`), your totals will completely desync, resulting in critical accounting bugs.

### The Fix

We need to purge the redundant `available_funds` column from the `companies` table to guarantee that the `wallets` engine remains the **single source of truth** across your ecosystem.

Create a new migration file by running:

```bash
php artisan make:migration drop_available_funds_from_companies_table

```

Open up that newly created migration file and update its code to match this exactly:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Drop the duplicate financial source of truth
            $table->dropColumn('available_funds');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Restore the column in case of a rollback
            $table->decimal('available_funds', 15, 2)->default(0.00)->after('points_name');
        });
    }
};

```

### Apply the Change

Save the file and run your migrations:

```bash
php artisan migrate

```

Let me know once that's applied successfully, and we'll fix the core order property mismatches causing your frontend layout to read `undefined` values!