<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Processed-webhook-event log for delivery-level deduplication.
     *
     * Razorpay retries webhook deliveries. Payment-level fulfilment is already
     * idempotent (fulfilOnce); this table adds a cheap fast-path so a
     * redelivered event is acknowledged without re-running any logic — plus a
     * permanent audit trail of everything the webhook received.
     *
     * Rows are written AFTER an event is handled (record-after-success), so a
     * crash mid-handling still allows the retry to reprocess safely.
     */
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key')->unique(); // razorpay:payment.captured:pay_xxx
            $table->string('provider')->default('razorpay');
            $table->string('event');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
