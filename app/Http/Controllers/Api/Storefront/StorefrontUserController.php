<?php
namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\VoucherCode;
use Illuminate\Http\Request;

class StorefrontUserController extends Controller
{
    public function wallet(Request $request)
    {
        $user   = $request->user();
        $wallet = $user->wallet;

        if (! $wallet) {
            return response()->json(['balance' => 0, 'transactions' => []]);
        }

        $transactions = $wallet->transactions()
            ->orderBy('created_at', 'desc')
            ->select('id', 'type', 'amount', 'description', 'created_at', 'expires_at')
            ->paginate(15);

        return response()->json([
            'balance'      => $wallet->balance,
            'transactions' => $transactions,
        ]);
    }

    public function orders(Request $request)
    {
        $user = $request->user();

        $orders = Order::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->select('id', 'order_number', 'total_amount', 'points_used', 'fiat_paid', 'coupon_code', 'discount_amount', 'status', 'created_at')
            ->with(['items' => function ($query) {
                $query->select('id', 'order_id', 'product_name', 'quantity', 'total_price');
            }])
            ->paginate(10);

        return response()->json($orders);
    }

    /**
     * Full order detail for the storefront order-detail page: financial
     * breakdown, per-item prices + delivery status, shipping + tracking,
     * billing snapshot, and any voucher codes this order issued.
     *
     * SIGNATURE NOTE: the route is /storefront/{slug}/user/orders/{orderNumber}
     * and Laravel binds route parameters to controller arguments POSITIONALLY
     * (after the Request). The signature MUST therefore declare $slug BEFORE
     * $orderNumber - with just (Request, $orderNumber) the slug
     * ("vishal-enterprises") was bound to $orderNumber and the real order
     * number was silently dropped, so the lookup 404'd even though the order
     * existed and belonged to the caller.
     */
    public function showOrder(Request $request, $slug, $orderNumber)
    {
        $user = $request->user();

        $order = Order::where('user_id', $user->id)
            ->where('order_number', $orderNumber)
            ->with('items')
            ->firstOrFail();

        $vouchers = VoucherCode::where('issued_to_user_id', $user->id)
            ->whereIn('product_id', $order->items->pluck('product_id')->filter())
            ->orderBy('issued_at', 'desc')
            ->get(['id', 'product_id', 'code', 'pin', 'is_used', 'issued_at', 'expires_at']);

        return response()->json([
            'data' => [
                'order_number'              => $order->order_number,
                'status'                    => $order->status,
                'created_at'                => $order->created_at,
                'total_amount'              => $order->total_amount,
                'gst_total'                 => $order->gst_total,
                'discount_amount'           => $order->discount_amount,
                'coupon_code'               => $order->coupon_code,
                'points_used'               => $order->points_used,
                'fiat_paid'                 => $order->fiat_paid,
                'payment_gateway_reference' => $order->payment_gateway_reference,
                'shipping_name'             => $order->shipping_name,
                'shipping_mobile'           => $order->shipping_mobile,
                'shipping_address_line_1'   => $order->shipping_address_line_1,
                'shipping_city'             => $order->shipping_city,
                'shipping_state'            => $order->shipping_state,
                'shipping_pincode'          => $order->shipping_pincode,
                'logistics_provider'        => $order->logistics_provider,
                'tracking_number'           => $order->tracking_number,
                'billing_address_snapshot'  => $order->billing_address_snapshot,
                'items'                     => $order->items->map(function ($item) {
                    return [
                        'product_name'    => $item->product_name,
                        'quantity'        => $item->quantity,
                        'unit_price'      => $item->unit_price,
                        'total_price'     => $item->total_price,
                        'delivery_status' => $item->delivery_status,
                    ];
                }),
                'vouchers'                  => $vouchers->map(function ($voucher) {
                    return [
                        'code'       => $voucher->code,
                        'pin'        => $voucher->pin,
                        'issued_at'  => $voucher->issued_at,
                        'expires_at' => $voucher->expires_at,
                    ];
                })->values(),
            ],
        ]);
    }

    public function vouchers(Request $request)
    {
        $user = $request->user();

        $vouchers = VoucherCode::where('issued_to_user_id', $user->id)
            ->with('product:id,name,main_image')
            ->orderBy('issued_at', 'desc')
            ->paginate(15);

        return response()->json($vouchers);
    }
}
