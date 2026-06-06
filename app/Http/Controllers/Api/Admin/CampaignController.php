<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCampaignJob;
use App\Models\Campaign;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $campaigns = Campaign::where('company_id', $request->user()->company_id)
            ->with(['vertical:id,name', 'event:id,title'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($campaigns);
    }

    public function show(Request $request, $id)
    {
        $campaign = Campaign::where('company_id', $request->user()->company_id)
            ->with(['vertical', 'event'])
            ->findOrFail($id);

        return response()->json(['data' => $campaign]);
    }

    public function store(Request $request)
    {
        $user    = $request->user();
        $company = $user->company;

        $validated = $request->validate([
            'name'                      => 'required|string|max:255',
            'description'               => 'nullable|string',
            'vertical_id'               => 'required|exists:verticals,id',
            'event_id'                  => 'nullable|required_without:custom_event_name|exists:events,id',
            'custom_event_name'         => 'nullable|required_without:event_id|string|max:255',
            'distribution_type'         => 'required|in:online,bulk',

            'reward_type'               => 'required_if:distribution_type,online|in:points,code,link',
            'recipient_ids'             => 'required_if:distribution_type,online|array',
            'recipient_ids.*'           => 'exists:users,id',
            'reward_value_per_user'     => 'required_if:distribution_type,online|numeric|min:1',
            'starts_at'                 => 'nullable|date',
            'expires_at'                => 'nullable|date|after:starts_at',

            'event_address'             => 'required_if:distribution_type,bulk|string',
            'event_date'                => 'required_if:distribution_type,bulk|date',
            'inquiry_notes'             => 'nullable|string',
            'bulk_items'                => 'required_if:distribution_type,bulk|array',
            'bulk_items.*.product_id'   => 'required|integer',
            'bulk_items.*.product_name' => 'required|string',
            'bulk_items.*.variant_id'   => 'nullable|integer',
            'bulk_items.*.variant_name' => 'nullable|string',
            'bulk_items.*.quantity'     => 'required|integer|min:1',

            'config_json'               => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $totalCost         = 0;
            $totalRecipients   = 0;
            $status            = 'processing';
            $walletTransaction = null;

            if ($validated['distribution_type'] === 'online') {
                $totalRecipients = count($validated['recipient_ids']);
                $totalCost       = $totalRecipients * $validated['reward_value_per_user'];

                $walletTransaction = $company->wallet->debit(
                    amount: $totalCost,
                    description: "Campaign Escrow Lock: {$validated['name']}"
                );
            } else {
                $status = 'inquiry_pending';
            }

            $campaign = Campaign::create([
                'company_id'         => $company->id,
                'created_by_user_id' => $user->id,
                'vertical_id'        => $validated['vertical_id'],
                'event_id'           => $validated['event_id'] ?? null,
                'custom_event_name'  => $validated['custom_event_name'] ?? null,
                'name'               => $validated['name'],
                'description'        => $validated['description'] ?? null,
                'distribution_type'  => $validated['distribution_type'],
                'reward_type'        => $validated['reward_type'] ?? null,
                'budget_locked'      => $totalCost,
                'total_budget'       => $totalCost,
                'total_recipients'   => $totalRecipients,
                'config_json'        => array_merge($validated['config_json'] ?? [], [
                    'event_address' => $validated['event_address'] ?? null,
                    'event_date'    => $validated['event_date'] ?? null,
                    'inquiry_notes' => $validated['inquiry_notes'] ?? null,
                    'bulk_items'    => $validated['bulk_items'] ?? null,
                ]),
                'status'             => $status,
                'starts_at'          => $validated['starts_at'] ?? now(),
                'expires_at'         => $validated['expires_at'] ?? null,
            ]);

            if ($walletTransaction) {
                $walletTransaction->update([
                    'reference_type' => get_class($campaign),
                    'reference_id'   => $campaign->id,
                ]);
            }

            DB::commit();

            if ($campaign->distribution_type === 'online') {
                ProcessCampaignJob::dispatch(
                    $campaign->id,
                    $validated['recipient_ids'],
                    $validated['reward_value_per_user']
                );
            }

            return response()->json([
                'message' => $campaign->distribution_type === 'online'
                    ? 'Campaign successfully submitted and is now processing.'
                    : 'Wholesale inquiry submitted successfully. Our team will contact you soon.',
                'data'    => $campaign,
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create campaign.',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    public function cancel(Request $request, $id)
    {
        $campaign = Campaign::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        if (in_array($campaign->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'Cannot cancel a completed or already cancelled campaign.'], 400);
        }
        if ($campaign->reward_type === 'points' && $campaign->budget_locked <= 0) {
            return response()->json([
                'message' => 'Cannot cancel this campaign because the points have already been distributed to users.',
            ], 400);
        }

        DB::beginTransaction();
        try {
            if ($campaign->budget_locked > 0) {
                $campaign->company->wallet->credit(
                    amount: $campaign->budget_locked,
                    description: "Refund for Cancelled Campaign: {$campaign->name}"
                );
            }

            $campaign->entitlements()->where('is_claimed', false)->update([
                'expires_at' => now(),
            ]);

            $campaign->update([
                'status'        => 'cancelled',
                'budget_locked' => 0,
            ]);

            DB::commit();
            return response()->json(['message' => 'Campaign cancelled and remaining funds refunded.']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to cancel campaign.'], 500);
        }
    }
}
