<?php
namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignEntitlement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProcessCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    protected $campaignId;
    protected $recipientIds;
    protected $rewardValue;

    public function __construct(int $campaignId, array $recipientIds, float $rewardValue)
    {
        $this->campaignId   = $campaignId;
        $this->recipientIds = $recipientIds;
        $this->rewardValue  = $rewardValue;
    }

    public function handle(): void
    {
        $campaign = Campaign::findOrFail($this->campaignId);
        if (in_array($campaign->status, ['cancelled', 'completed'])) {
            return;
        }

        $processedUserIds = $campaign->entitlements()
            ->whereIn('issued_to_user_id', $this->recipientIds)
            ->pluck('issued_to_user_id')
            ->toArray();

        $remainingUserIds = array_diff($this->recipientIds, $processedUserIds);
        if (empty($remainingUserIds)) {
            $campaign->update(['status' => $campaign->starts_at->isFuture() ? 'scheduled' : 'active']);
            return;
        }
        $chunks = array_chunk($remainingUserIds, 500);

        foreach ($chunks as $chunk) {
            $entitlements = [];

            foreach ($chunk as $userId) {
                $token = null;
                $code  = null;

                if ($campaign->reward_type === 'link') {
                    $token = Str::random(64); 
                } elseif ($campaign->reward_type === 'code') {
                    $code = strtoupper(Str::random(8));
                }

                $entitlements[] = [
                    'campaign_id'       => $campaign->id,
                    'issued_to_user_id' => $userId,
                    'reward_value'      => $this->rewardValue,
                    'claim_token'       => $token,
                    'claim_code'        => $code,
                    'is_claimed'        => false,
                    'expires_at'        => $campaign->expires_at,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            }

            CampaignEntitlement::insert($entitlements);
            if ($campaign->reward_type === 'points') {
                $users = \App\Models\User::whereIn('id', $chunk)->get();

                foreach ($users as $user) {
                    $wallet = $user->wallet()->firstOrCreate([], ['balance' => 0.00]);
                    $wallet->credit(
                        amount: $this->rewardValue,
                        description: "Reward from Campaign: {$campaign->name}"
                    );
                }

                CampaignEntitlement::where('campaign_id', $campaign->id)
                    ->whereIn('issued_to_user_id', $chunk)
                    ->update([
                        'is_claimed' => true,
                        'claimed_at' => now(),
                    ]);

                $campaign->decrement('budget_locked', $this->rewardValue * count($chunk));
            }
        }

        $newStatus = $campaign->starts_at->isFuture() ? 'scheduled' : 'active';

        $campaign->update(['status' => $newStatus]);

        if ($newStatus === 'active') {
            DispatchCampaignCommsJob::dispatch($campaign->id);
        }
    }
}
