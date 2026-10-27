<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private function getAllowedVerticalIds($user, $requestedProgram = 'all'): array
    {
        if ($user->user_type === 'sub_admin') {
            $allowedVerticals = $user->managedVerticals()->pluck('verticals.id', 'verticals.slug');
        } else {
            $allowedVerticals = $user->company->verticals()->pluck('verticals.id', 'verticals.slug');
        }

        if ($requestedProgram !== 'all' && isset($allowedVerticals[$requestedProgram])) {
            return [$allowedVerticals[$requestedProgram]];
        }

        return $allowedVerticals->values()->toArray();
    }

    private function parseDateRange(Request $request): array
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $to   = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : Carbon::now()->endOfDay();

        return [$from, $to];
    }

    /** One row per point entitlement, summarising the original campaign grant credits. */
    private function pointGrantCreditSummaryQuery()
    {
        return DB::table('transactions as point_grant_credits')
            ->selectRaw('
                point_grant_credits.reference_id as entitlement_id,
                COUNT(*) as credit_count,
                SUM(point_grant_credits.amount) as points_sent,
                SUM(CASE
                    WHEN COALESCE(point_grant_credits.allocation_tracking_complete, 0) = 0 THEN 1
                    ELSE 0
                END) as untracked_credit_count
            ')
            ->where('point_grant_credits.type', 'credit')
            ->where('point_grant_credits.reference_type', CampaignEntitlement::class)
            ->whereNull('point_grant_credits.reversal_of_transaction_id')
            ->groupBy('point_grant_credits.reference_id');
    }

    /** Per-campaign issued-point cohort and its historical allocation coverage. */
    private function pointCohortSummaryQuery(
        int $companyId,
        array $allowedVerticalIds,
        Carbon $fromDate,
        Carbon $toDate
    ) {
        return DB::table('campaign_entitlements as point_entitlements')
            ->join('campaigns as point_campaigns', 'point_campaigns.id', '=', 'point_entitlements.campaign_id')
            ->leftJoinSub($this->pointGrantCreditSummaryQuery(), 'point_grant_summary', function ($join) {
                $join->on('point_grant_summary.entitlement_id', '=', 'point_entitlements.id');
            })
            ->where('point_campaigns.company_id', $companyId)
            ->whereIn('point_campaigns.vertical_id', $allowedVerticalIds)
            ->where('point_campaigns.reward_type', 'points')
            ->whereBetween('point_entitlements.created_at', [$fromDate, $toDate])
            ->where(function ($query) {
                $query->where('point_entitlements.is_claimed', true)
                    ->orWhereNotNull('point_grant_summary.entitlement_id');
            })
            ->groupBy('point_campaigns.id')
            ->selectRaw('
                point_campaigns.id as campaign_id,
                SUM(CASE
                    WHEN COALESCE(point_grant_summary.credit_count, 0) > 0
                        THEN COALESCE(point_grant_summary.points_sent, 0)
                    WHEN point_entitlements.is_claimed = 1
                        THEN point_entitlements.reward_value
                    ELSE 0
                END) as points_sent,
                SUM(CASE
                    WHEN COALESCE(point_grant_summary.credit_count, 0) > 0
                        AND COALESCE(point_grant_summary.untracked_credit_count, 0) > 0 THEN 1
                    ELSE 0
                END) as untracked_credit_count,
                SUM(CASE
                    WHEN COALESCE(point_grant_summary.credit_count, 0) = 0
                        AND point_entitlements.is_claimed = 1 THEN 1
                    ELSE 0
                END) as missing_credit_count
            ');
    }

    /**
     * Unaggregated, exactly sourced point allocations used by successful orders.
     * The optional cohort filter limits the source grants to entitlements issued
     * within the selected period.
     */
    private function pointRedemptionRowsQuery(
        int $companyId,
        array $allowedVerticalIds,
        Carbon $fromDate,
        Carbon $toDate,
        bool $restrictSourceCohortToPeriod = false
    ) {
        $query = DB::table('wallet_transaction_allocations as point_allocations')
            ->join('transactions as point_debits', 'point_debits.id', '=', 'point_allocations.debit_transaction_id')
            ->join('transactions as point_credits', 'point_credits.id', '=', 'point_allocations.credit_transaction_id')
            ->join('campaign_entitlements as point_entitlements', function ($join) {
                $join->on('point_entitlements.id', '=', 'point_credits.reference_id')
                    ->where('point_credits.reference_type', '=', CampaignEntitlement::class)
                    ->where('point_credits.type', '=', 'credit')
                    ->where('point_credits.allocation_tracking_complete', '=', true);
            })
            ->join('campaigns as point_campaigns', 'point_campaigns.id', '=', 'point_entitlements.campaign_id')
            ->join('orders as point_orders', 'point_orders.id', '=', 'point_debits.reference_id')
            ->join('wallets as point_wallets', 'point_wallets.id', '=', 'point_debits.wallet_id')
            ->where('point_debits.type', 'debit')
            ->where('point_debits.reference_type', Order::class)
            ->where('point_campaigns.company_id', $companyId)
            ->whereIn('point_campaigns.vertical_id', $allowedVerticalIds)
            ->where('point_campaigns.reward_type', 'points')
            ->where('point_orders.company_id', $companyId)
            ->whereIn('point_orders.status', ['paid', 'processing', 'shipped', 'completed'])
            ->whereBetween('point_orders.created_at', [$fromDate, $toDate])
            ->where('point_wallets.walletable_type', User::class)
            ->whereColumn('point_wallets.walletable_id', 'point_orders.user_id')
            ->whereColumn('point_credits.wallet_id', 'point_debits.wallet_id')
            ->whereColumn('point_entitlements.issued_to_user_id', 'point_orders.user_id');

        if ($restrictSourceCohortToPeriod) {
            $query->whereBetween('point_entitlements.created_at', [$fromDate, $toDate]);
        }

        return $query;
    }

    public function getOverview(Request $request)
    {
        $user                = $request->user();
        $rewardType          = $request->query('reward_type', 'points');
        $allowedVerticalIds  = $this->getAllowedVerticalIds($user, $request->query('program', 'all'));
        [$fromDate, $toDate] = $this->parseDateRange($request);
        $paidStatuses        = ['paid', 'processing', 'shipped', 'completed'];

        if (empty($allowedVerticalIds)) {
            return response()->json([
                'data' => [
                    'total_sent'      => 0,
                    'total_redeemed'  => 0,
                    'redemption_rate' => $rewardType === 'points' ? null : 0,
                    'total_campaigns' => 0,
                ],
            ]);
        }

        if ($rewardType === 'points') {
            $pointCohortRows = $this->pointCohortSummaryQuery(
                (int) $user->company_id,
                $allowedVerticalIds,
                $fromDate,
                $toDate
            )->get();
            $totalSent                      = (float) $pointCohortRows->sum('points_sent');
            $hasIncompletePointGrantHistory = $pointCohortRows->sum('untracked_credit_count') > 0
            || $pointCohortRows->sum('missing_credit_count') > 0;
        } else {
            $totalSent = (float) DB::table('campaign_entitlements')
                ->join('campaigns', 'campaign_entitlements.campaign_id', '=', 'campaigns.id')
                ->where('campaigns.company_id', $user->company_id)
                ->whereIn('campaigns.vertical_id', $allowedVerticalIds)
                ->where('campaigns.reward_type', $rewardType)
                ->whereBetween('campaign_entitlements.created_at', [$fromDate, $toDate])
                ->sum(DB::raw('1'));
        }

        if ($rewardType === 'points') {
            // The headline total is every successful order's points_used in the
            // selected order-date range, regardless of whether its source grant
            // can be attributed. The rate below is campaign-source-cohort based.
            $totalRedeemed = (float) DB::table('orders')
                ->where('company_id', $user->company_id)
                ->whereIn('status', $paidStatuses)
                ->where('points_used', '>', 0)
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->whereExists(function ($query) use ($allowedVerticalIds) {
                    $query->select(DB::raw(1))
                        ->from('rewardee_profiles')
                        ->whereColumn('rewardee_profiles.user_id', 'orders.user_id')
                        ->whereIn('rewardee_profiles.vertical_id', $allowedVerticalIds);
                })
                ->sum('points_used');

            // Cohort points are attributed only from tracked campaign grants to
            // successful orders in this period. Any legacy, unallocated grant in
            // the issued cohort makes the complete rate unavailable.
            $attributedByCampaign = $this->pointRedemptionRowsQuery(
                (int) $user->company_id,
                $allowedVerticalIds,
                $fromDate,
                $toDate,
                true
            )
                ->selectRaw('point_campaigns.id as campaign_id, SUM(point_allocations.amount) as redeemed_points')
                ->groupBy('point_campaigns.id');

            $attributedRedeemed = (float) DB::query()
                ->fromSub($attributedByCampaign, 'attributed_point_redemptions')
                ->sum('redeemed_points');

            $redemptionRate = $hasIncompletePointGrantHistory
                ? null
                : ($totalSent > 0 ? round(($attributedRedeemed / $totalSent) * 100, 1) : 0);
        } else {
            // Codes/links are traceable to their originating entitlement. This
            // is a cohort rate: claimed entitlements among those issued in range.
            $totalRedeemed = (float) DB::table('campaign_entitlements')
                ->join('campaigns', 'campaign_entitlements.campaign_id', '=', 'campaigns.id')
                ->where('campaigns.company_id', $user->company_id)
                ->whereIn('campaigns.vertical_id', $allowedVerticalIds)
                ->where('campaigns.reward_type', $rewardType)
                ->where('campaign_entitlements.is_claimed', true)
                ->whereBetween('campaign_entitlements.created_at', [$fromDate, $toDate])
                ->count();

            $redemptionRate = $totalSent > 0
                ? round(($totalRedeemed / $totalSent) * 100)
                : 0;
        }

        // This card is a current snapshot, independent of the selected activity range.
        $activeCampaigns = Campaign::where('company_id', $user->company_id)
            ->whereIn('vertical_id', $allowedVerticalIds)
            ->where('reward_type', $rewardType)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->count();

        return response()->json([
            'data' => [
                'total_sent'      => $totalSent,
                'total_redeemed'  => $totalRedeemed,
                'redemption_rate' => $redemptionRate,
                'total_campaigns' => $activeCampaigns,
            ],
        ]);
    }

    public function getTrends(Request $request)
    {
        $user                = $request->user();
        $rewardType          = $request->query('reward_type', 'points');
        $allowedVerticalIds  = $this->getAllowedVerticalIds($user, $request->query('program', 'all'));
        [$fromDate, $toDate] = $this->parseDateRange($request);

        if (empty($allowedVerticalIds)) {
            return response()->json(['data' => []]);
        }

        if ($rewardType === 'points') {
            $sentQuery = DB::table('campaign_entitlements as point_entitlements')
                ->join('campaigns as point_campaigns', 'point_campaigns.id', '=', 'point_entitlements.campaign_id')
                ->leftJoinSub($this->pointGrantCreditSummaryQuery(), 'point_grant_summary', function ($join) {
                    $join->on('point_grant_summary.entitlement_id', '=', 'point_entitlements.id');
                })
                ->where('point_campaigns.company_id', $user->company_id)
                ->whereIn('point_campaigns.vertical_id', $allowedVerticalIds)
                ->where('point_campaigns.reward_type', 'points')
                ->whereBetween('point_entitlements.created_at', [$fromDate, $toDate])
                ->where(function ($query) {
                    $query->where('point_entitlements.is_claimed', true)
                        ->orWhereNotNull('point_grant_summary.entitlement_id');
                })
                ->selectRaw("DATE_FORMAT(point_entitlements.created_at, '%Y-%m') as month, SUM(CASE WHEN COALESCE(point_grant_summary.credit_count, 0) > 0 THEN COALESCE(point_grant_summary.points_sent, 0) WHEN point_entitlements.is_claimed = 1 THEN point_entitlements.reward_value ELSE 0 END) as sent")
                ->groupByRaw("DATE_FORMAT(point_entitlements.created_at, '%Y-%m')")
                ->get()
                ->keyBy('month');
        } else {
            $sentQuery = DB::table('campaign_entitlements')
                ->join('campaigns', 'campaign_entitlements.campaign_id', '=', 'campaigns.id')
                ->where('campaigns.company_id', $user->company_id)
                ->whereIn('campaigns.vertical_id', $allowedVerticalIds)
                ->where('campaigns.reward_type', $rewardType)
                ->whereBetween('campaign_entitlements.created_at', [$fromDate, $toDate])
                ->selectRaw("DATE_FORMAT(campaign_entitlements.created_at, '%Y-%m') as month, COUNT(campaign_entitlements.id) as sent")
                ->groupByRaw("DATE_FORMAT(campaign_entitlements.created_at, '%Y-%m')")
                ->get()
                ->keyBy('month');
        }

        if ($rewardType === 'points') {
            // Only source-linked campaign point allocations are reported here.
            // Historic credits without allocation rows are intentionally excluded.
            $redeemedQuery = $this->pointRedemptionRowsQuery(
                (int) $user->company_id,
                $allowedVerticalIds,
                $fromDate,
                $toDate
            )
                ->selectRaw("DATE_FORMAT(point_orders.created_at, '%Y-%m') as month, SUM(point_allocations.amount) as redeemed")
                ->groupByRaw("DATE_FORMAT(point_orders.created_at, '%Y-%m')")
                ->get()
                ->keyBy('month');
        } else {
            // Code/link chart values describe the claim status of each issue-month cohort.
            $redeemedQuery = DB::table('campaign_entitlements')
                ->join('campaigns', 'campaign_entitlements.campaign_id', '=', 'campaigns.id')
                ->where('campaigns.company_id', $user->company_id)
                ->whereIn('campaigns.vertical_id', $allowedVerticalIds)
                ->where('campaigns.reward_type', $rewardType)
                ->where('campaign_entitlements.is_claimed', true)
                ->whereBetween('campaign_entitlements.created_at', [$fromDate, $toDate])
                ->selectRaw(
                    "DATE_FORMAT(campaign_entitlements.created_at, '%Y-%m') as month, "
                    . 'COUNT(campaign_entitlements.id) as redeemed'
                )
                ->groupByRaw("DATE_FORMAT(campaign_entitlements.created_at, '%Y-%m')")
                ->get()
                ->keyBy('month');
        }

        $allMonths = $sentQuery->keys()->merge($redeemedQuery->keys())->unique()->sort();

        $trends = $allMonths->map(function ($month) use ($sentQuery, $redeemedQuery) {
            return [
                'name'     => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'sent'     => (float) ($sentQuery->get($month)->sent ?? 0),
                'redeemed' => (float) ($redeemedQuery->get($month)->redeemed ?? 0),
            ];
        })->values();

        return response()->json(['data' => $trends]);
    }

    public function getDistribution(Request $request)
    {
        $user                = $request->user();
        $rewardType          = $request->query('reward_type', 'points');
        $allowedVerticalIds  = $this->getAllowedVerticalIds($user, $request->query('program', 'all'));
        [$fromDate, $toDate] = $this->parseDateRange($request);

        if (empty($allowedVerticalIds)) {
            return response()->json(['data' => []]);
        }

        if ($rewardType === 'points') {
            $distribution = DB::table('campaign_entitlements as point_entitlements')
                ->join('campaigns as point_campaigns', 'point_campaigns.id', '=', 'point_entitlements.campaign_id')
                ->leftJoinSub($this->pointGrantCreditSummaryQuery(), 'point_grant_summary', function ($join) {
                    $join->on('point_grant_summary.entitlement_id', '=', 'point_entitlements.id');
                })
                ->leftJoin('events as point_events', 'point_campaigns.event_id', '=', 'point_events.id')
                ->where('point_campaigns.company_id', $user->company_id)
                ->whereIn('point_campaigns.vertical_id', $allowedVerticalIds)
                ->where('point_campaigns.reward_type', 'points')
                ->whereBetween('point_entitlements.created_at', [$fromDate, $toDate])
                ->where(function ($query) {
                    $query->where('point_entitlements.is_claimed', true)
                        ->orWhereNotNull('point_grant_summary.entitlement_id');
                })
                ->selectRaw("COALESCE(point_events.title, point_campaigns.custom_event_name, 'Other') as name, SUM(CASE WHEN COALESCE(point_grant_summary.credit_count, 0) > 0 THEN COALESCE(point_grant_summary.points_sent, 0) WHEN point_entitlements.is_claimed = 1 THEN point_entitlements.reward_value ELSE 0 END) as value")
                ->groupByRaw("COALESCE(point_events.title, point_campaigns.custom_event_name, 'Other')")
                ->orderByDesc('value')
                ->get();
        } else {
            $distribution = DB::table('campaign_entitlements')
                ->join('campaigns', 'campaign_entitlements.campaign_id', '=', 'campaigns.id')
                ->leftJoin('events', 'campaigns.event_id', '=', 'events.id')
                ->where('campaigns.company_id', $user->company_id)
                ->whereIn('campaigns.vertical_id', $allowedVerticalIds)
                ->where('campaigns.reward_type', $rewardType)
                ->whereBetween('campaign_entitlements.created_at', [$fromDate, $toDate])
                ->selectRaw("COALESCE(events.title, campaigns.custom_event_name, 'Other') as name, COUNT(campaign_entitlements.id) as value")
                ->groupByRaw("COALESCE(events.title, campaigns.custom_event_name, 'Other')")
                ->orderByDesc('value')
                ->get();
        }

        $distribution = $distribution->map(function ($item) {
            return [
                'name'  => $item->name,
                'value' => (float) $item->value,
            ];
        });

        return response()->json(['data' => $distribution]);
    }

    public function getTopRecipients(Request $request)
    {
        $user                = $request->user();
        $companyId           = $user->company_id;
        $rewardType          = $request->query('reward_type', 'points');
        $allowedVerticalIds  = $this->getAllowedVerticalIds($user, $request->query('program', 'all'));
        [$fromDate, $toDate] = $this->parseDateRange($request);
        $paidStatuses        = ['paid', 'processing', 'shipped', 'completed'];

        $emptySummary = [
            'total_recipients'     => 0,
            'total_points_balance' => 0,
            'total_orders'         => 0,
        ];

        if (empty($allowedVerticalIds)) {
            return response()->json(['data' => [], 'summary' => $emptySummary]);
        }

        $periodEntitlements = function ($query) use (
            $companyId,
            $allowedVerticalIds,
            $rewardType,
            $fromDate,
            $toDate
        ) {
            $query->whereBetween('campaign_entitlements.created_at', [$fromDate, $toDate]);

            if ($rewardType === 'points') {
                // For point rewards, the entitlement is marked claimed when its
                // wallet credit is issued; pending entitlements are not receipts.
                $query->where('campaign_entitlements.is_claimed', true);
            }

            $query->whereHas('campaign', function ($campaignQuery) use (
                $companyId,
                $allowedVerticalIds,
                $rewardType
            ) {
                $campaignQuery->where('campaigns.company_id', $companyId)
                    ->whereIn('campaigns.vertical_id', $allowedVerticalIds)
                    ->where('campaigns.reward_type', $rewardType);
            });
        };

        $periodPointRedemptions = function ($query) use (
            $companyId,
            $paidStatuses,
            $fromDate,
            $toDate
        ) {
            $query->where('orders.company_id', $companyId)
                ->whereIn('orders.status', $paidStatuses)
                ->where('orders.points_used', '>', 0)
                ->whereBetween('orders.created_at', [$fromDate, $toDate]);
        };

        $periodPaidOrders = function ($query) use ($companyId, $paidStatuses, $fromDate, $toDate) {
            $query->where('orders.company_id', $companyId)
                ->whereIn('orders.status', $paidStatuses)
                ->whereBetween('orders.created_at', [$fromDate, $toDate]);
        };

        $baseQuery = User::query()
            ->select('users.*')
            ->where('users.company_id', $companyId)
            ->where('users.user_type', 'rewardee')
            ->where('users.is_active', true)
            ->whereHas('rewardeeProfile', function ($profileQuery) use ($allowedVerticalIds) {
                $profileQuery->whereIn('vertical_id', $allowedVerticalIds);
            })
            ->where(function ($activityQuery) use (
                $periodEntitlements,
                $periodPointRedemptions,
                $rewardType
            ) {
                $activityQuery->whereHas('campaignEntitlements', $periodEntitlements);

                if ($rewardType === 'points') {
                    $activityQuery->orWhereHas('orders', $periodPointRedemptions);
                }
            });

        $totalRecipients = (int) (clone $baseQuery)->count('users.id');
        $summary         = [
            'total_recipients'     => $totalRecipients,
            'total_points_balance' => 0,
            'total_orders'         => 0,
        ];

        if ($rewardType === 'points') {
            $recipientIds = (clone $baseQuery)->select('users.id');

            $summary['total_points_balance'] = (float) DB::table('wallets')
                ->where('walletable_type', User::class)
                ->whereIn('walletable_id', $recipientIds)
                ->sum('balance');

            $summary['total_orders'] = (int) Order::query()
                ->where('company_id', $companyId)
                ->whereIn('status', $paidStatuses)
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->whereIn('user_id', (clone $recipientIds))
                ->count();
        }

        $query = (clone $baseQuery)->with(['rewardeeProfile.vertical']);

        if ($rewardType === 'points') {
            $query->with('wallet')
                ->withCount([
                    'orders as redemptions_count' => $periodPointRedemptions,
                    'orders as orders_count'      => $periodPaidOrders,
                ])
                ->orderByDesc(
                    Wallet::select('balance')
                        ->whereColumn('walletable_id', 'users.id')
                        ->where('walletable_type', User::class)
                        ->limit(1)
                );
        } else {
            $query->withCount([
                'campaignEntitlements as received_count'    => $periodEntitlements,
                'campaignEntitlements as redemptions_count' => function ($entitlementQuery) use ($periodEntitlements) {
                    $periodEntitlements($entitlementQuery);
                    $entitlementQuery->where('campaign_entitlements.is_claimed', true);
                },
                'orders as orders_count'                    => $periodPaidOrders,
            ])
                ->orderByDesc('received_count');
        }

        $multiplier = (float) ($user->company->point_multiplier ?? 1.0);
        if ($multiplier <= 0) {
            $multiplier = 1.0;
        }

        $recipients = $query->take(15)->get()->map(function ($recipient) use ($multiplier, $rewardType) {
            if ($rewardType === 'points') {
                $value       = (float) ($recipient->wallet->balance ?? 0);
                $redemptions = (int) ($recipient->redemptions_count ?? 0);
            } else {
                $value       = (int) ($recipient->received_count ?? 0);
                $redemptions = (int) ($recipient->redemptions_count ?? 0);
            }

            $tierBase = $value / $multiplier;

            return [
                'id'             => $recipient->id,
                'name'           => trim($recipient->first_name . ' ' . $recipient->last_name),
                'email'          => $recipient->email,
                'type'           => $recipient->rewardeeProfile->vertical->name ?? 'Unknown',
                'points_balance' => $value,
                'redemptions'    => $redemptions,
                'orders'         => (int) ($recipient->orders_count ?? 0),
                'tier'           => $tierBase > 5000
                    ? 'Platinum'
                    : ($tierBase > 2000 ? 'Gold' : 'Silver'),
            ];
        })->values();

        return response()->json([
            'data'    => $recipients,
            'summary' => $summary,
        ]);
    }

    public function getCampaigns(Request $request)
    {
        $user                = $request->user();
        $rewardType          = $request->query('reward_type', 'points');
        $allowedVerticalIds  = $this->getAllowedVerticalIds($user, $request->query('program', 'all'));
        [$fromDate, $toDate] = $this->parseDateRange($request);

        if (empty($allowedVerticalIds)) {
            return response()->json(['data' => []]);
        }

        $issuedInRange = function ($query) use ($fromDate, $toDate) {
            $query->whereBetween('campaign_entitlements.created_at', [$fromDate, $toDate]);
        };

        if ($rewardType === 'points') {
            $pointCohortSummary = $this->pointCohortSummaryQuery(
                (int) $user->company_id,
                $allowedVerticalIds,
                $fromDate,
                $toDate
            );
            $pointRedemptionSummary = $this->pointRedemptionRowsQuery(
                (int) $user->company_id,
                $allowedVerticalIds,
                $fromDate,
                $toDate,
                true
            )
                ->selectRaw('point_campaigns.id as campaign_id, SUM(point_allocations.amount) as redeemed_points')
                ->groupBy('point_campaigns.id');

            $campaigns = Campaign::query()
                ->select([
                    'campaigns.*',
                    'point_cohort.points_sent as point_cohort_sent',
                    'point_cohort.untracked_credit_count',
                    'point_cohort.missing_credit_count',
                    'point_redemptions.redeemed_points as attributed_redeemed_points',
                ])
                ->leftJoinSub($pointCohortSummary, 'point_cohort', function ($join) {
                    $join->on('point_cohort.campaign_id', '=', 'campaigns.id');
                })
                ->leftJoinSub($pointRedemptionSummary, 'point_redemptions', function ($join) {
                    $join->on('point_redemptions.campaign_id', '=', 'campaigns.id');
                })
                ->where('campaigns.company_id', $user->company_id)
                ->whereIn('campaigns.vertical_id', $allowedVerticalIds)
                ->where('campaigns.reward_type', 'points')
                ->whereNotNull('point_cohort.campaign_id')
                ->whereHas('entitlements', $issuedInRange)
                ->orderByDesc('campaigns.created_at')
                ->get()
                ->map(function ($campaign) {
                    $sent                 = (float) ($campaign->point_cohort_sent ?? 0);
                    $redeemed             = (float) ($campaign->attributed_redeemed_points ?? 0);
                    $attributionAvailable = (int) ($campaign->untracked_credit_count ?? 0) === 0
                    && (int) ($campaign->missing_credit_count ?? 0) === 0;

                    return [
                        'id'                    => 'C-' . $campaign->id,
                        'name'                  => $campaign->name,
                        'startDate'             => $campaign->starts_at
                            ? Carbon::parse($campaign->starts_at)->format('Y-m-d')
                            : 'N/A',
                        'endDate'               => $campaign->expires_at
                            ? Carbon::parse($campaign->expires_at)->format('Y-m-d')
                            : 'N/A',
                        'sent'                  => $sent,
                        'redeemed'              => $attributionAvailable ? $redeemed : null,
                        'redemption_rate'       => $attributionAvailable
                            ? ($sent > 0 ? round(($redeemed / $sent) * 100, 1) : 0)
                            : null,
                        'attribution_available' => $attributionAvailable,
                        'status'                => ucfirst($campaign->status),
                    ];
                })
                ->values();
        } else {
            $campaigns = Campaign::where('company_id', $user->company_id)
                ->whereIn('vertical_id', $allowedVerticalIds)
                ->where('reward_type', $rewardType)
                ->whereHas('entitlements', $issuedInRange)
                ->withCount(['entitlements as total_sent' => $issuedInRange])
                ->withCount(['entitlements as total_redeemed' => function ($query) use ($issuedInRange) {
                    $issuedInRange($query);
                    $query->where('campaign_entitlements.is_claimed', true);
                }])
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($campaign) {
                    return [
                        'id'                    => 'C-' . $campaign->id,
                        'name'                  => $campaign->name,
                        'startDate'             => $campaign->starts_at
                            ? Carbon::parse($campaign->starts_at)->format('Y-m-d')
                            : 'N/A',
                        'endDate'               => $campaign->expires_at
                            ? Carbon::parse($campaign->expires_at)->format('Y-m-d')
                            : 'N/A',
                        'sent'                  => (int) $campaign->total_sent,
                        'redeemed'              => (int) $campaign->total_redeemed,
                        'redemption_rate'       => (int) $campaign->total_sent > 0
                            ? round(((int) $campaign->total_redeemed / (int) $campaign->total_sent) * 100, 1)
                            : 0,
                        'attribution_available' => true,
                        'status'                => ucfirst($campaign->status),
                    ];
                })
                ->values();
        }

        return response()->json(['data' => $campaigns]);
    }

    public function getProductReports(Request $request)
    {
        $user                = $request->user();
        $allowedVerticalIds  = $this->getAllowedVerticalIds($user, $request->query('program', 'all'));
        [$fromDate, $toDate] = $this->parseDateRange($request);

        if (empty($allowedVerticalIds)) {
            return response()->json(['top_products' => [], 'category_data' => []]);
        }

        $orderQueryClosure = function ($q) use ($user, $allowedVerticalIds, $fromDate, $toDate) {
            $q->where('company_id', $user->company_id)
                ->whereIn('status', ['paid', 'processing', 'shipped', 'completed'])
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->whereExists(function ($uq) use ($allowedVerticalIds) {
                    $uq->select(DB::raw(1))
                        ->from('rewardee_profiles')
                        ->whereColumn('rewardee_profiles.user_id', 'orders.user_id')
                        ->whereIn('rewardee_profiles.vertical_id', $allowedVerticalIds);
                });
        };

        // FIXED: Calculate proportional points used per item instead of summing fiat catalog price
        $topProducts = OrderItem::whereHas('order', $orderQueryClosure)
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->join('rewardee_profiles', 'users.id', '=', 'rewardee_profiles.user_id')
            ->join('verticals', 'rewardee_profiles.vertical_id', '=', 'verticals.id')
            ->selectRaw('
                products.id,
                products.name,
                categories.name as category,
                verticals.name as recipientType,
                SUM(CASE
                    WHEN orders.total_amount > 0
                    THEN (order_items.total_price / orders.total_amount) * orders.points_used
                    ELSE 0
                END) as pointsRedeemed
            ')
            ->groupBy('products.id', 'products.name', 'categories.name', 'verticals.name')
            ->orderByDesc('pointsRedeemed')
            ->take(10)
            ->get();

        $categoryData = OrderItem::whereHas('order', $orderQueryClosure)
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw('
                categories.name,
                SUM(CASE
                    WHEN orders.total_amount > 0
                    THEN (order_items.total_price / orders.total_amount) * orders.points_used
                    ELSE 0
                END) as redeemed
            ')
            ->groupBy('categories.name')
            ->get();

        return response()->json([
            'top_products'  => $topProducts,
            'category_data' => $categoryData,
        ]);
    }

    public function getRecentActivity(Request $request)
    {
        $user                = $request->user();
        $rewardType          = $request->query('reward_type', 'points');
        $allowedVerticalIds  = $this->getAllowedVerticalIds($user, $request->query('program', 'all'));
        [$fromDate, $toDate] = $this->parseDateRange($request);
        $paidStatuses        = ['paid', 'processing', 'shipped', 'completed'];

        if (empty($allowedVerticalIds)) {
            return response()->json(['data' => []]);
        }

        $entitlementQuery = CampaignEntitlement::query()
            ->select('campaign_entitlements.*')
            ->join('campaigns', 'campaign_entitlements.campaign_id', '=', 'campaigns.id')
            ->where('campaigns.company_id', $user->company_id)
            ->whereIn('campaigns.vertical_id', $allowedVerticalIds)
            ->where('campaigns.reward_type', $rewardType)
            ->with(['user', 'campaign.event']);

        $sentActivity = (clone $entitlementQuery)
            ->whereBetween('campaign_entitlements.created_at', [$fromDate, $toDate])
            ->when($rewardType === 'points', function ($query) {
                // Point entitlements are marked claimed when the wallet credit is issued.
                $query->where('campaign_entitlements.is_claimed', true);
            })
            ->orderByDesc('campaign_entitlements.created_at')
            ->take(15)
            ->get()
            ->map(function ($entitlement) use ($rewardType) {
                $createdAt = $entitlement->created_at;
                $eventName = $entitlement->campaign?->event?->title ?? $entitlement->campaign?->custom_event_name ?? 'Campaign Reward';

                return [
                    'id'       => 'TX-' . $entitlement->id . '-SENT',
                    'user'     => $entitlement->user
                        ? trim($entitlement->user->first_name . ' ' . $entitlement->user->last_name)
                        : 'Unknown',
                    'type'     => 'Sent',
                    'value'    => $rewardType === 'points'
                        ? (float) $entitlement->reward_value
                        : ($entitlement->claim_code ?? 'LINK'),
                    'reason'   => $eventName,
                    'date'     => Carbon::parse($createdAt)->format('Y-m-d'),
                    '_sort_at' => $createdAt,
                ];
            });

        if ($rewardType === 'points') {
            // Points are marked claimed when credited. Actual redemptions are
            // fulfilled catalog orders that spent points, not that flag.
            $redeemedActivity = Order::query()
                ->with(['user:id,first_name,last_name'])
                ->where('company_id', $user->company_id)
                ->whereIn('status', $paidStatuses)
                ->where('points_used', '>', 0)
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->whereHas('user.rewardeeProfile', function ($profileQuery) use ($allowedVerticalIds) {
                    $profileQuery->whereIn('vertical_id', $allowedVerticalIds);
                })
                ->orderByDesc('created_at')
                ->take(15)
                ->get()
                ->map(function ($order) {
                    $createdAt = $order->created_at;

                    return [
                        'id'       => 'ORD-' . $order->id,
                        'user'     => $order->user
                            ? trim($order->user->first_name . ' ' . $order->user->last_name)
                            : 'Unknown',
                        'type'     => 'Redeemed',
                        'value'    => (float) $order->points_used,
                        'reason'   => 'Catalog order ' . $order->order_number,
                        'date'     => Carbon::parse($createdAt)->format('Y-m-d'),
                        '_sort_at' => $createdAt,
                    ];
                });
        } else {
            // For code/link rows, show claims on the date they were claimed,
            // not on the original issue date.
            $redeemedActivity = (clone $entitlementQuery)
                ->where('campaign_entitlements.is_claimed', true)
                ->whereNotNull('campaign_entitlements.claimed_at')
                ->whereBetween('campaign_entitlements.claimed_at', [$fromDate, $toDate])
                ->orderByDesc('campaign_entitlements.claimed_at')
                ->take(15)
                ->get()
                ->map(function ($entitlement) {
                    $claimedAt = $entitlement->claimed_at;
                    $eventName = $entitlement->campaign?->event?->title ?? $entitlement->campaign?->custom_event_name ?? 'Campaign Reward';

                    return [
                        'id'       => 'TX-' . $entitlement->id . '-REDEEMED',
                        'user'     => $entitlement->user
                            ? trim($entitlement->user->first_name . ' ' . $entitlement->user->last_name)
                            : 'Unknown',
                        'type'     => 'Redeemed',
                        'value'    => $entitlement->claim_code ?? 'LINK',
                        'reason'   => $eventName,
                        'date'     => Carbon::parse($claimedAt)->format('Y-m-d'),
                        '_sort_at' => $claimedAt,
                    ];
                });
        }

        $activity = $sentActivity
            ->concat($redeemedActivity)
            ->sortByDesc('_sort_at')
            ->take(15)
            ->map(function ($item) {
                unset($item['_sort_at']);
                return $item;
            })
            ->values();

        return response()->json(['data' => $activity]);
    }
}
