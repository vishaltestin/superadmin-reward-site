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

    public function showOrder(Request $request, $orderNumber)
    {
        $user = $request->user();

        $order = Order::where('user_id', $user->id)
            ->where('order_number', $orderNumber)
            ->with('items')
            ->firstOrFail();

        return response()->json([
            'data' => [
                'order_number'            => $order->order_number,
                'status'                  => $order->status,
                'points_used'             => $order->points_used,
                'created_at'              => $order->created_at,
                'shipping_name'           => $order->shipping_name,
                'shipping_address_line_1' => $order->shipping_address_line_1,
                'shipping_city'           => $order->shipping_city,
                'shipping_state'          => $order->shipping_state,
                'shipping_pincode'        => $order->shipping_pincode,
                'logistics_provider'      => $order->logistics_provider,
                'tracking_number'         => $order->tracking_number,
                'items'                   => $order->items->map(function ($item) {
                    return [
                        'product_name'    => $item->product_name,
                        'quantity'        => $item->quantity,
                        'delivery_status' => $item->delivery_status,
                    ];
                }),
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
