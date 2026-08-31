<?php
namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Models\VoucherClaim;
use App\Models\VoucherCode;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoucherClaimController extends Controller
{
    private function tenantProduct(string $slug, string $productSlug): ?Product
    {
        $company = Company::where('alias', $slug)->where('is_active', true)->first();

        if (! $company) {
            return null;
        }

        $allowedCategoryIds = $company->categories()->pluck('categories.id')->toArray();

        return Product::where('slug', $productSlug)
            ->where('is_active', true)
            ->whereIn('category_id', $allowedCategoryIds)
            ->whereNotIn('category_id', $company->hidden_category_ids ?? [])
            ->whereNotIn('id', $company->hidden_product_ids ?? [])
            ->whereDoesntHave('customCompanies', function ($q) use ($company) {
                $q->where('company_id', $company->id)->where('is_excluded', true);
            })
            ->first();
    }

    public function claim(Request $request)
    {
        $productSlug = $request->route('productSlug');
        $slug        = $request->route('slug');
        $user        = $request->user();

        $product = $this->tenantProduct($slug, $productSlug);

        if (! $product) {
            return response()->json(['message' => 'Voucher not found.'], 404);
        }

        if ($product->type !== 'digital') {
            return response()->json(['message' => 'Invalid voucher.'], 422);
        }

        try {
            [$claim, $voucherCode] = DB::transaction(function () use ($product, $user) {

                $existing = VoucherClaim::where('product_id', $product->id)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    throw new Exception('Voucher already claimed.');
                }

                $claim = VoucherClaim::create([
                    'product_id' => $product->id,
                    'user_id'    => $user->id,
                    'claimed_at' => now(),
                ]);

                $voucherCode = VoucherCode::where('product_id', $product->id)
                    ->whereNull('issued_to_user_id')
                    ->where('is_used', false)
                    ->orderByRaw('expires_at IS NULL ASC, expires_at ASC') 
                    ->lockForUpdate()
                    ->first();

                if ($voucherCode) {
                    $voucherCode->update([
                        'issued_to_user_id' => $user->id,
                        'issued_at'         => now(),
                    ]);
                }

                return [$claim, $voucherCode];
            });
        } catch (QueryException $e) {
            return response()->json(['message' => 'Voucher already claimed.'], 422);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $typeData = $product->type_data ?? [];

        return response()->json([
            'message'         => 'Voucher claimed successfully.',
            'coupon_code'     => $voucherCode?->code ?? ($typeData['couponCode'] ?? null),
            'pin'             => $voucherCode?->pin ?? null,
            'redemption_link' => $typeData['redemptionLink'] ?? null,
            'valid_until'     => $voucherCode?->expires_at ?? ($typeData['validUntil'] ?? null),
            'claimed_at'      => $claim->claimed_at,
        ]);
    }

    public function myClaims(Request $request)
    {
        $claims = VoucherClaim::with('product')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $codes = VoucherCode::where('issued_to_user_id', $request->user()->id)
            ->get()
            ->keyBy('product_id');

        return response()->json(
            $claims->map(function ($claim) use ($codes) {
                $code     = $codes->get($claim->product_id);
                $typeData = $claim->product->type_data ?? [];

                return [
                    'id'          => $claim->id,
                    'product'     => $claim->product,
                    'claimed_at'  => $claim->claimed_at,
                    'coupon_code' => $code?->code ?? ($typeData['couponCode'] ?? null),
                    'pin'         => $code?->pin ?? null,
                    'valid_until' => $code?->expires_at ?? ($typeData['validUntil'] ?? null),
                ];
            })->values()
        );
    }
}
