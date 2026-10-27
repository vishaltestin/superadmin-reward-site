<?php
namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\CampaignEntitlement;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClawbackExpiredCampaigns extends Command
{
    protected $signature   = 'campaigns:clawback';
    protected $description = 'Refunds unused budget from expired campaigns back to the Company.';

    public function handle()
    {
        $expiredEntitlements = CampaignEntitlement::where('is_claimed', false)
            ->where('expires_at', '<=', now())
            ->where('reward_value', '>', 0)
            ->with('campaign.company.wallet')
            ->get();

        $refundCount = 0;

        foreach ($expiredEntitlements as $entitlement) {
            try {
                DB::transaction(function () use ($entitlement, &$refundCount) {
                    $lockedEntitlement = CampaignEntitlement::where('id', $entitlement->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $lockedEntitlement || $lockedEntitlement->is_claimed) {
                        return;
                    }

                    $refundAmount = round((float) $lockedEntitlement->reward_value, 2);
                    if ($refundAmount <= 0) {
                        // Zero-value entitlements have already been clawed back
                        // (or never had a refundable balance); do not log +0 rows.
                        return;
                    }

                    $campaign = $lockedEntitlement->campaign;
                    $wallet   = $campaign?->company?->wallet;
                    if (! $campaign || ! $wallet) {
                        throw new Exception("Missing campaign company wallet for entitlement {$lockedEntitlement->id}.");
                    }

                    $lockedEntitlement->update(['reward_value' => 0]);
                    $campaign->decrement('budget_locked', $refundAmount);

                    $wallet->credit(
                        amount: $refundAmount,
                        description: "Clawback Refund from Expired Reward (User ID: {$lockedEntitlement->issued_to_user_id}; Entitlement ID: {$lockedEntitlement->id})",
                        reference: $campaign
                    );
                    $refundCount++;
                });
            } catch (Exception $e) {
                Log::error("Failed clawback for entitlement {$entitlement->id}: " . $e->getMessage());
            }
        }

        $completedCampaigns = Campaign::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'completed']);

        $this->info("Clawback complete. Processed {$refundCount} refunds and completed {$completedCampaigns} campaigns.");

        return self::SUCCESS;
    }
}
