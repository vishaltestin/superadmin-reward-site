<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CampaignEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class ClaimController extends Controller
{
    /**
     * 1. Validate the Magic Link and load the Single Page Checkout Data
     */
    public function validateToken(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        $entitlement = CampaignEntitlement::where('claim_token', $request->token)
            ->with(['campaign', 'user:id,first_name,last_name,email'])
            ->first();

        if (!$entitlement) {
            return response()->json(['message' => 'Invalid or malformed reward link.'], 404);
        }

        if ($entitlement->is_claimed) {
            return response()->json(['message' => 'This reward has already been claimed.'], 400);
        }

        if ($entitlement->expires_at && $entitlement->expires_at->isPast()) {
            return response()->json(['message' => 'This reward link has expired.'], 400);
        }

        if ($entitlement->campaign->status !== 'active') {
            return response()->json(['message' => 'This campaign is not currently active.'], 400);
        }

        // Return the payload needed to render the Single Page Checkout
        return response()->json([
            'reward_value' => $entitlement->reward_value,
            'recipient' => $entitlement->user,
            'campaign_config' => $entitlement->campaign->config_json, // Contains allowed products/categories and Landing Page IDs
        ]);
    }

    /**
     * 2. Execute the Claim (The "Pay + Points" Upsell)
     */
    public function executeClaim(Request $request)
    {
        $user = $request->user(); // From Sanctum middleware

        $validated = $request->validate([
            'token' => 'required|string',
            'product_variant_id' => 'required|exists:product_variants,id',
            'shipping_address' => 'required|array', // Assume frontend passes raw address data
            'fiat_paid' => 'required|numeric|min:0', 
            'payment_reference' => 'nullable|string', // e.g., Razorpay ID if they paid extra
        ]);

        try {
            DB::beginTransaction();

            // Strict Identity Lock: Lock the row for update to prevent double-clicks
            $entitlement = CampaignEntitlement::where('claim_token', $validated['token'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($entitlement->issued_to_user_id !== $user->id) {
                throw new Exception("Unauthorized. This reward belongs to another employee.");
            }

            if ($entitlement->is_claimed) {
                throw new Exception("Reward already claimed.");
            }

            $variant = ProductVariant::with('product')->findOrFail($validated['product_variant_id']);

            // (Optional) Here you would add logic to verify if $variant->product->category_id 
            // is inside the allowed categories from $entitlement->campaign->config_json

            // 1. Create the Order
            $order = Order::create([
                'company_id' => $entitlement->campaign->company_id,
                'user_id' => $user->id,
                'total_amount' => $variant->selling_price,
                'points_used' => $entitlement->reward_value,
                'fiat_paid' => $validated['fiat_paid'], // Any extra they paid
                'payment_gateway_reference' => $validated['payment_reference'],
                'status' => 'paid',
                'shipping_name' => $user->first_name . ' ' . $user->last_name,
                'shipping_mobile' => $user->mobile,
                'shipping_address_line_1' => $validated['shipping_address']['address_line_1'],
                'shipping_city' => $validated['shipping_address']['city'],
                'shipping_state' => $validated['shipping_address']['state'],
                'shipping_pincode' => $validated['shipping_address']['pincode'],
            ]);

            // 2. Create the Order Item
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product->name . ' - ' . $variant->name,
                'quantity' => 1,
                'unit_price' => $variant->selling_price,
                'total_price' => $variant->selling_price,
                'delivery_status' => 'pending',
            ]);

            // 3. Update the Entitlement
            $entitlement->update([
                'is_claimed' => true,
                'claimed_at' => now()
            ]);

            // 4. Deduct from the Campaign's Locked Budget (Release the Escrow)
            $entitlement->campaign->decrement('budget_locked', $entitlement->reward_value);

            DB::commit();

            return response()->json([
                'message' => 'Reward claimed successfully! Your order has been placed.',
                'order_number' => $order->order_number
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}