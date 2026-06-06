<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignEntitlement;
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

    public function getOverview(Request $request)
    {
        $user                = $request->user();
        $rewardType          = $request->query('reward_type', 'points');
        $allowedVerticalIds  = $this->getAllowedVerticalIds($user, $request->query('program', 'all'));
        [$fromDate, $toDate] = $this->parseDateRange($request);

        if (empty($allowedVerticalIds)) {
            return response()->json(['data' => ['total_sent' => 0, 'total_redeemed' => 0, 'redemption_rate' => 0, 'total_campaigns' => 0]]);
        }

        $totalSent = (float) DB::table('campaign_entitlements')
            ->join('campaigns', 'campaign_entitlements.campaign_id', '=', 'campaigns.id')
            ->where('campaigns.company_id', $user->company_id)
            ->whereIn('campaigns.vertical_id', $allowedVerticalIds)
            ->where('campaigns.reward_type', $rewardType)
            ->whereBetween('campaign_entitlements.created_at', [$fromDate, $toDate])
            ->sum(DB::raw($rewardType === 'points' ? 'campaign_entitlements.reward_value' : '1'));

        $totalRedeemed = 0;

        if ($rewardType === 'points') {
            $totalRedeemed = (float) DB::table('orders')
                ->where('company_id', $user->company_id)
                ->whereIn('status', ['paid', 'processing', 'shipped', 'completed'])
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->whereExists(function ($query) use ($allowedVerticalIds) {
                    $query->select(DB::raw(1))
                        ->from('rewardee_profiles')
                        ->whereColumn('rewardee_profiles.user_id', 'orders.user_id')
                        ->whereIn('rewardee_profiles.vertical_id', $allowedVerticalIds);
                })
                ->sum('points_used');
        } else {
            $totalRedeemed = (float) DB::table('campaign_entitlements')
                ->join('campaigns', 'campaign_entitlements.campaign_id', '=', 'campaigns.id')
                ->where('campaigns.company_id', $user->company_id)
                ->whereIn('campaigns.vertical_id', $allowedVerticalIds)
                ->where('campaigns.reward_type', $rewardType)
                ->where('campaign_entitlements.is_claimed', true)
                ->whereBetween('campaign_entitlements.created_at', [$fromDate, $toDate])
                ->count();
        }

        $redemptionRate = $totalSent > 0 ? round(($totalRedeemed / $totalSent) * 100) : 0;

        $activeCampaigns = Campaign::where('company_id', $user->company_id)
            ->whereIn('vertical_id', $allowedVerticalIds)
            ->where('reward_type', $rewardType)
            ->whereIn('status', ['active', 'completed'])
            ->whereBetween('starts_at', [$fromDate, $toDate])
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

        // 1. Get "Sent" Data (Always from campaign_entitlements)
        $sentQuery = DB::table('campaign_entitlements')
            ->join('campaigns', 'campaign_entitlements.campaign_id', '=', 'campaigns.id')
            ->where('campaigns.company_id', $user->company_id)
            ->whereIn('campaigns.vertical_id', $allowedVerticalIds)
            ->where('campaigns.reward_type', $rewardType)
            ->whereBetween('campaign_entitlements.created_at', [$fromDate, $toDate])
            ->selectRaw("DATE_FORMAT(campaign_entitlements.created_at, '%Y-%m') as month, " .
                ($rewardType === 'points' ? "SUM(campaign_entitlements.reward_value)" : "COUNT(campaign_entitlements.id)") . " as sent")
            ->groupByRaw("DATE_FORMAT(campaign_entitlements.created_at, '%Y-%m')")
            ->get()
            ->keyBy('month');

        // 2. Get "Redeemed" Data (Logic splits based on reward type)
        if ($rewardType === 'points') {
            // For points, pull from ACTUAL orders placed at the storefront
            $redeemedQuery = DB::table('orders')
                ->where('company_id', $user->company_id)
                ->whereIn('status', ['paid', 'processing', 'shipped', 'completed'])
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->whereExists(function ($query) use ($allowedVerticalIds) {
                    $query->select(DB::raw(1))
                        ->from('rewardee_profiles')
                        ->whereColumn('rewardee_profiles.user_id', 'orders.user_id')
                        ->whereIn('rewardee_profiles.vertical_id', $allowedVerticalIds);
                })
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(points_used) as redeemed")
                ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
                ->get()
                ->keyBy('month');
        } else {
            // For codes/links, pull from claimed entitlements
            $redeemedQuery = DB::table('campaign_entitlements')
                ->join('campaigns', 'campaign_entitlements.campaign_id', '=', 'campaigns.id')
                ->where('campaigns.company_id', $user->company_id)
                ->whereIn('campaigns.vertical_id', $allowedVerticalIds)
                ->where('campaigns.reward_type', $rewardType)
                ->where('campaign_entitlements.is_claimed', true)
                ->whereBetween('campaign_entitlements.created_at', [$fromDate, $toDate])
                ->selectRaw("DATE_FORMAT(campaign_entitlements.created_at, '%Y-%m') as month, COUNT(campaign_entitlements.id) as redeemed")
                ->groupByRaw("DATE_FORMAT(campaign_entitlements.created_at, '%Y-%m')")
                ->get()
                ->keyBy('month');
        }

        // 3. Merge the months together (In case a month had redemptions but no sent points, or vice versa)
        $allMonths = $sentQuery->keys()->merge($redeemedQuery->keys())->unique()->sort();

        // 4. Format for Shadcn / Recharts
        $trends = $allMonths->map(function ($month) use ($sentQuery, $redeemedQuery) {
            return [
                'name'     => Carbon::createFromFormat('Y-m', $month)->format('M'),
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

        $sumField = $rewardType === 'points' ? 'SUM(campaign_entitlements.reward_value)' : 'COUNT(campaign_entitlements.id)';

        $distribution = DB::table('campaign_entitlements')
            ->join('campaigns', 'campaign_entitlements.campaign_id', '=', 'campaigns.id')
            ->leftJoin('events', 'campaigns.event_id', '=', 'events.id')
            ->where('campaigns.company_id', $user->company_id)
            ->whereIn('campaigns.vertical_id', $allowedVerticalIds)
            ->where('campaigns.reward_type', $rewardType)
            ->whereBetween('campaign_entitlements.created_at', [$fromDate, $toDate])
            ->selectRaw("COALESCE(events.title, campaigns.custom_event_name, 'Other') as name, $sumField as value")
            ->groupByRaw("COALESCE(events.title, campaigns.custom_event_name, 'Other')")
            ->orderByDesc('value')
            ->get()
            ->map(function ($item) {
                return [
                    'name'  => $item->name,
                    'value' => (float) $item->value,
                ];
            });

        return response()->json(['data' => $distribution]);
    }

    public function getTopRecipients(Request $request)
    {
        $user               = $request->user();
        $rewardType         = $request->query('reward_type', 'points');
        $allowedVerticalIds = $this->getAllowedVerticalIds($user, $request->query('program', 'all'));

        if (empty($allowedVerticalIds)) {
            return response()->json(['data' => []]);
        }

        $query = User::select('users.*')
            ->where('users.company_id', $user->company_id)
            ->where('users.user_type', 'rewardee')
            ->whereExists(function ($q) use ($allowedVerticalIds) {
                $q->select(DB::raw(1))
                  ->from('rewardee_profiles')
                  ->whereColumn('rewardee_profiles.user_id', 'users.id')
                  ->whereIn('rewardee_profiles.vertical_id', $allowedVerticalIds);
            })
            ->with(['rewardeeProfile.vertical']);

        // DYNAMIC LOGIC: Split database logic based on Reward Type
        if ($rewardType === 'points') {
            $query->with('wallet')
                ->withCount('orders as redemptions_count')
                ->orderByDesc(
                    Wallet::select('balance')
                        ->whereColumn('walletable_id', 'users.id')
                        ->where('walletable_type', User::class)
                        ->limit(1)
                );
        } else {
            // For Codes & Links, ONLY show users who actually received them
            $query->whereHas('campaignEntitlements', function ($q) use ($rewardType) {
                    $q->whereHas('campaign', function ($cq) use ($rewardType) {
                        $cq->where('reward_type', $rewardType);
                    });
                })
                ->withCount([
                    // Count total codes/links sent
                    'campaignEntitlements as received_count' => function ($q) use ($rewardType) {
                        $q->whereHas('campaign', function ($cq) use ($rewardType) {
                            $cq->where('reward_type', $rewardType);
                        });
                    },
                    // Count total codes/links actually clicked/claimed
                    'campaignEntitlements as redemptions_count' => function ($q) use ($rewardType) {
                        $q->where('is_claimed', true)
                          ->whereHas('campaign', function ($cq) use ($rewardType) {
                              $cq->where('reward_type', $rewardType);
                          });
                    }
                ])
                ->orderByDesc('received_count'); // Sort by who got the most codes
        }

        $multiplier = $user->company->point_multiplier ?? 1.0;

        $recipients = $query->take(15)->get()->map(function ($u) use ($multiplier, $rewardType) {
            
            // Map the correct values to the JSON response based on the tab
            if ($rewardType === 'points') {
                $balance = (float) ($u->wallet->balance ?? 0);
                $redemptions = $u->redemptions_count;
            } else {
                $balance = $u->received_count ?? 0;
                $redemptions = $u->redemptions_count ?? 0;
            }
            
            $tierBase = $balance / $multiplier; 

            return [
                'id'             => $u->id,
                'name'           => trim($u->first_name . ' ' . $u->last_name),
                'email'          => $u->email,
                'type'           => $u->rewardeeProfile->vertical->name ?? 'Unknown',
                // Keep the JSON key as 'points_balance' so the React table renders it correctly across all tabs!
                'points_balance' => $balance, 
                'redemptions'    => $redemptions,
                'tier'           => $tierBase > 5000 ? 'Platinum' : ($tierBase > 2000 ? 'Gold' : 'Silver'),
            ];
        });

        return response()->json(['data' => $recipients]);
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

        $campaigns = Campaign::where('company_id', $user->company_id)
            ->whereIn('vertical_id', $allowedVerticalIds)
            ->where('reward_type', $rewardType)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->withCount('entitlements as total_sent')
            ->withSum('entitlements as total_points_sent', 'reward_value')
            ->withCount(['entitlements as total_redeemed' => function ($q) {
                $q->where('is_claimed', true);
            }])
            ->withSum(['entitlements as total_points_redeemed' => function ($q) {
                $q->where('is_claimed', true);
            }], 'reward_value')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($c) use ($rewardType) {
                return [
                    'id'        => 'C-' . $c->id,
                    'name'      => $c->name,
                    'startDate' => Carbon::parse($c->starts_at)->format('Y-m-d'),
                    'endDate'   => $c->expires_at ? Carbon::parse($c->expires_at)->format('Y-m-d') : 'N/A',
                    'sent'      => $rewardType === 'points' ? ((float) $c->total_points_sent ?? 0) : $c->total_sent,
                    'redeemed'  => $rewardType === 'points' ? ((float) $c->total_points_redeemed ?? 0) : $c->total_redeemed,
                    'status'    => ucfirst($c->status),
                ];
            });

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

        if (empty($allowedVerticalIds)) {
            return response()->json(['data' => []]);
        }

        $activity = CampaignEntitlement::select('campaign_entitlements.*')
            ->join('campaigns', 'campaign_entitlements.campaign_id', '=', 'campaigns.id')
            ->where('campaigns.company_id', $user->company_id)
            ->whereIn('campaigns.vertical_id', $allowedVerticalIds)
            ->where('campaigns.reward_type', $rewardType)
            ->whereBetween('campaign_entitlements.created_at', [$fromDate, $toDate])
            ->with(['user', 'campaign.event'])
            ->orderByDesc('campaign_entitlements.created_at')
            ->take(15)
            ->get()
            ->map(function ($tx) use ($rewardType) {
                return [
                    'id'     => 'TX-' . $tx->id,
                    'user'   => $tx->user ? trim($tx->user->first_name . ' ' . $tx->user->last_name) : 'Unknown',
                    'type'   => $tx->is_claimed ? 'Redeemed' : 'Sent',
                    'value'  => $rewardType === 'points' ? (float) $tx->reward_value : ($tx->claim_code ?? 'LINK'),
                    'reason' => $tx->campaign->event->title ?? $tx->campaign->custom_event_name ?? 'Campaign Reward',
                    'status' => $tx->is_claimed ? 'Redeemed' : ($tx->expires_at && Carbon::parse($tx->expires_at)->isPast() ? 'Expired' : 'Sent'),
                    'date'   => Carbon::parse($tx->created_at)->format('Y-m-d'),
                ];
            });

        return response()->json(['data' => $activity]);
    }
}
