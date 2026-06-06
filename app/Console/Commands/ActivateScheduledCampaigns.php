<?php
namespace App\Console\Commands;

use App\Jobs\DispatchCampaignCommsJob;
use App\Models\Campaign;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivateScheduledCampaigns extends Command
{
    protected $signature   = 'campaigns:activate';
    protected $description = 'Checks for scheduled campaigns, distributes points, and dispatches comms.';

    public function handle()
    {
        $campaigns = Campaign::with('entitlements.user')
            ->where('status', 'scheduled')
            ->where('starts_at', '<=', now())
            ->get();

        if ($campaigns->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($campaigns as $campaign) {
            DB::beginTransaction();
            try {
                if ($campaign->reward_type === 'points') {
                    $unclaimedEntitlements  = $campaign->entitlements()->where('is_claimed', false)->get();
                    $totalPointsDistributed = 0;

                    foreach ($unclaimedEntitlements as $entitlement) {
                        $user = $entitlement->user;
                        if ($user) {
                            $wallet = $user->wallet()->firstOrCreate([], ['balance' => 0.00]);
                            $wallet->credit(
                                amount: $entitlement->reward_value,
                                description: "Reward from Scheduled Campaign: {$campaign->name}"
                            );

                            $entitlement->update([
                                'is_claimed' => true,
                                'claimed_at' => now(),
                            ]);

                            $totalPointsDistributed += $entitlement->reward_value;
                        }
                    }

                    if ($totalPointsDistributed > 0) {
                        $campaign->decrement('budget_locked', $totalPointsDistributed);
                    }
                }

                $campaign->update(['status' => 'active']);
                DB::commit();

                if ($campaign->distribution_type === 'online') {
                    DispatchCampaignCommsJob::dispatch($campaign->id);
                }

                $this->info("Activated campaign ID: {$campaign->id}");

            } catch (Exception $e) {
                DB::rollBack();
                Log::error("Failed to activate scheduled campaign ID {$campaign->id}: " . $e->getMessage());
                $this->error("Failed to activate campaign ID: {$campaign->id}");
            }
        }

        return self::SUCCESS;
    }
}
