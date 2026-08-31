<?php
namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignEntitlement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Distributes campaign rewards to recipients.
 *
 * Idempotency guarantees (a retried job after a mid-flight crash can never
 * double-credit or double-issue):
 *   1. Per-chunk database transactions — entitlement insert, points credit and
 *      is_claimed flag commit together or not at all.
 *   2. Existing entitlements are re-checked INSIDE the transaction, so a retry
 *      that races its own previous partial work skips already-processed users.
 *   3. Every points credit carries reference_type/reference_id pointing at the
 *      entitlement it paid for — a durable idempotency key that is checked
 *      before crediting.
 *
 * Tenant guard: recipients are filtered to the campaign company's own active
 * rewardees, even if bad input reached the job.
 */
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

        $validRecipientIds = User::whereIn('id', $this->recipientIds)
            ->where('company_id', $campaign->company_id)
            ->where('user_type', 'rewardee')
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        $processedUserIds = $campaign->entitlements()
            ->whereIn('issued_to_user_id', $validRecipientIds)
            ->pluck('issued_to_user_id')
            ->toArray();

        $remainingUserIds = array_diff($validRecipientIds, $processedUserIds);

        if (empty($remainingUserIds)) {
            $campaign->update(['status' => $campaign->starts_at->isFuture() ? 'scheduled' : 'active']);
            return;
        }

        foreach (array_chunk($remainingUserIds, 500) as $chunk) {
            DB::transaction(function () use ($campaign, $chunk) {

                $alreadyProcessed = $campaign->entitlements()
                    ->whereIn('issued_to_user_id', $chunk)
                    ->pluck('issued_to_user_id')
                    ->toArray();

                $todo = array_values(array_diff($chunk, $alreadyProcessed));

                if (empty($todo)) {
                    return;
                }

                $entitlements = $this->insertEntitlements($campaign, $todo);

                if ($campaign->reward_type === 'points') {
                    $credited = 0;

                    foreach ($entitlements as $entitlement) {
                        $alreadyCredited = Transaction::where('reference_type', CampaignEntitlement::class)
                            ->where('reference_id', $entitlement->id)
                            ->where('type', 'credit')
                            ->exists();

                        if ($alreadyCredited) {
                            continue;
                        }

                        $user = User::find($entitlement->issued_to_user_id);
                        if (! $user) {
                            continue;
                        }

                        $wallet = $user->wallet()->firstOrCreate([], ['balance' => 0.00]);
                        $wallet->credit(
                            amount: $this->rewardValue,
                            description: "Reward from Campaign: {$campaign->name}",
                            reference: $entitlement
                        );

                        $credited++;
                    }

                    CampaignEntitlement::where('campaign_id', $campaign->id)
                        ->whereIn('issued_to_user_id', $todo)
                        ->update([
                            'is_claimed' => true,
                            'claimed_at' => now(),
                        ]);

                    if ($credited > 0) {
                        $campaign->decrement('budget_locked', $this->rewardValue * $credited);
                    }
                }
            });
        }

        $campaign->refresh();

        $newStatus = $campaign->starts_at->isFuture() ? 'scheduled' : 'active';

        $campaign->update(['status' => $newStatus]);

        if ($newStatus === 'active') {
            DispatchCampaignCommsJob::dispatch($campaign->id);
        }
    }

    /**
     * Bulk-insert entitlement rows; on a unique-key collision (claim_code /
     * claim_token randomly colliding) fall back to per-row inserts with fresh
     * codes so one unlucky collision can't fail the whole chunk.
     *
     * @return CampaignEntitlement[] keyed by user id
     */
    private function insertEntitlements(Campaign $campaign, array $userIds): array
    {
        $rows = [];

        foreach ($userIds as $userId) {
            $token = null;
            $code  = null;

            if ($campaign->reward_type === 'link') {
                $token = Str::random(64);
            } elseif ($campaign->reward_type === 'code') {
                $code = strtoupper(Str::random(8));
            }

            $rows[] = [
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

        try {
            CampaignEntitlement::insert($rows);
        } catch (QueryException $e) {
            foreach ($rows as $index => $row) {
                for ($attempt = 0; $attempt < 3; $attempt++) {
                    try {
                        CampaignEntitlement::insert([$row]);
                        break;
                    } catch (QueryException $e2) {
                        if ($attempt === 2) {
                            unset($rows[$index]);
                            break;
                        }

                        if ($row['claim_code']) {
                            $row['claim_code'] = strtoupper(Str::random(8));
                        }
                        if ($row['claim_token']) {
                            $row['claim_token'] = Str::random(64);
                        }
                        $rows[$index] = $row;
                    }
                }
            }
        }

        $entitlements = [];

        $fresh = CampaignEntitlement::where('campaign_id', $campaign->id)
            ->whereIn('issued_to_user_id', $userIds)
            ->get();

        foreach ($fresh as $entitlement) {
            $entitlements[$entitlement->issued_to_user_id] = $entitlement;
        }

        return $entitlements;
    }
}
