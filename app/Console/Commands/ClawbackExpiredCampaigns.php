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
            ->with('campaign.company.wallet')
            ->get();

        $refundCount = 0;

        foreach ($expiredEntitlements as $entitlement) {
            try {
                DB::transaction(function () use ($entitlement, &$refundCount) {
                    $lockedEntitlement = CampaignEntitlement::where('id', $entitlement->id)
                        ->lockForUpdate()
                        ->first();

                    if ($lockedEntitlement && ! $lockedEntitlement->is_claimed) {
                        $refundAmount = $lockedEntitlement->reward_value;

                        $lockedEntitlement->update(['reward_value' => 0]);
                        $lockedEntitlement->campaign->decrement('budget_locked', $refundAmount);

                        $lockedEntitlement->campaign->company->wallet->credit(
                            amount: $refundAmount,
                            description: "Clawback Refund from Expired Reward (User ID: {$lockedEntitlement->issued_to_user_id})",
                            reference: $lockedEntitlement->campaign
                        );
                        $refundCount++;
                    }
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
