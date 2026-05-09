<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Campaign;
use App\Models\CampaignEntitlement;
use App\Jobs\DispatchCampaignCommsJob;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('landing-pages:clean-orphans')->dailyAt('02:00');


/**
 * 1. Activate Scheduled Campaigns (Runs Daily at 00:00)
 */
Schedule::call(function () {
    $campaigns = Campaign::where('status', 'scheduled')
        ->where('starts_at', '<=', now())
        ->get();

    foreach ($campaigns as $campaign) {
        $campaign->update(['status' => 'active']);
        
        // If it's a Points campaign, deposit the money right now!
        if ($campaign->reward_type === 'points') {
            // Logic to deposit points directly to user wallets
        } else {
            // Otherwise, send the emails with the links/codes
            DispatchCampaignCommsJob::dispatch($campaign->id);
        }
    }
})->daily();

/**
 * 2. The Clawback Job (Runs Daily at 01:00)
 * Refunds unused budget from expired campaigns back to the Company.
 */
Schedule::call(function () {
    $expiredEntitlements = CampaignEntitlement::where('is_claimed', false)
        ->where('expires_at', '<=', now())
        ->with('campaign.company.wallet')
        ->get();

    foreach ($expiredEntitlements as $entitlement) {
        DB::transaction(function () use ($entitlement) {
            // Lock the row to prevent race conditions during expiration
            $lockedEntitlement = CampaignEntitlement::where('id', $entitlement->id)
                                    ->lockForUpdate()
                                    ->first();

            if (!$lockedEntitlement->is_claimed) {
                // 1. Mark as expired (we can use a deleted_at soft delete or a status column, 
                // but for now we'll set reward_value to 0 to neutralize it)
                $refundAmount = $lockedEntitlement->reward_value;
                $lockedEntitlement->update(['reward_value' => 0]);

                // 2. Reduce the locked budget on the Campaign
                $lockedEntitlement->campaign->decrement('budget_locked', $refundAmount);

                // 3. Refund the Company Wallet safely
                $lockedEntitlement->campaign->company->wallet->credit(
                    amount: $refundAmount,
                    description: "Clawback Refund from Expired Reward (User ID: {$lockedEntitlement->issued_to_user_id})",
                    reference: $lockedEntitlement->campaign
                );
            }
        });
    }

    // Mark campaigns as completed if all entitlements are claimed or expired
    Campaign::where('status', 'active')
        ->where('expires_at', '<=', now())
        ->update(['status' => 'completed']);

})->dailyAt('01:00');