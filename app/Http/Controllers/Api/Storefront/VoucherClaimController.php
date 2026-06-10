<?php
namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\VoucherClaim;
use Illuminate\Http\Request;

class VoucherClaimController extends Controller
{
    public function claim(Request $request)
    {
        $productSlug = $request->route('productSlug');
        $slug        = $request->route('slug');
        $product     = Product::where('slug', $productSlug)
            ->where('is_active', true)
            ->firstOrFail();

        if ($product->type !== 'digital') {
            return response()->json([
                'message' => 'Invalid voucher.',
            ], 422);
        }

        if (
            VoucherClaim::where('product_id', $product->id)
            ->where('user_id', $request->user()->id)
            ->exists()
        ) {
            return response()->json([
                'message' => 'Voucher already claimed.',
            ], 422);
        }

        $claim = VoucherClaim::create([
            'product_id' => $product->id,
            'user_id'    => $request->user()->id,
            'claimed_at' => now(),
        ]);

        return response()->json([
            'message'         => 'Voucher claimed successfully.',
            'coupon_code'     => $product->type_data['couponCode'] ?? null,
            'redemption_link' => $product->type_data['redemptionLink'] ?? null,
            'valid_until'     => $product->type_data['validUntil'] ?? null,
            'claimed_at'      => $claim->claimed_at,
        ]);
    }

    public function myClaims(Request $request)
    {
        return VoucherClaim::with('product')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();
    }
}
