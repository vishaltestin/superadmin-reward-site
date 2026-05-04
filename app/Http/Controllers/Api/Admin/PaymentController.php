<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Get the current wallet balance and company point settings.
     */
    public function balance(Request $request)
    {
        $company = $request->user()->company->load('wallet');

        return response()->json([
            'balance' => $company->wallet->balance ?? 0,
            'points_name' => $company->points_name ?? 'Points',
            'point_multiplier' => $company->point_multiplier ?? 1.0, // e.g., 1 INR = 10 Points
        ]);
    }

    /**
     * Get paginated transaction history.
     */
    public function transactions(Request $request)
    {
        $company = $request->user()->company;
        
        // Ensure wallet exists before querying
        if (!$company->wallet) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $transactions = $company->wallet->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($transactions);
    }

    /**
     * MOCK Payment Gateway Top-up.
     * We will replace the inside of this method with DodoPayments later.
     */
    public function mockTopUp(Request $request)
    {
        // Only Business Heads should be able to add funds
        if ($request->user()->user_type !== 'business_head') {
            return response()->json(['message' => 'Only Business Heads can add funds.'], 403);
        }

        $validated = $request->validate([
            'fiat_amount' => 'required|numeric|min:100', // Minimum 100 INR/Fiat
        ]);

        $company = $request->user()->company;
        $wallet = $company->wallet;
        $multiplier = $company->point_multiplier ?? 1.0;

        // Calculate points based on multiplier
        $pointsToCredit = $validated['fiat_amount'] * $multiplier;

        DB::transaction(function () use ($wallet, $pointsToCredit, $validated) {
            // Using your beautifully designed Wallet model method!
            $wallet->credit(
                amount: $pointsToCredit, 
                description: 'Wallet Top-up (Mock)', 
                reference: null, 
                expiresAt: null, // Optional: Set to now()->addYear() if points expire
                fiatPaid: $validated['fiat_amount']
            );
        });

        return response()->json([
            'message' => "Successfully added {$pointsToCredit} points to your wallet.",
            'new_balance' => $wallet->fresh()->balance
        ]);
    }
}