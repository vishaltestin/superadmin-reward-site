<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Promotion;
use Carbon\Carbon;

class PromotionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load('company');
        $companyId = $user->company_id;
        $industry = $user->company->industry;

        $now = Carbon::now();

        $promotions = Promotion::where('is_active', true)
            // Handle Scheduling
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            // Handle Targeting Logic
            ->where(function ($query) use ($companyId, $industry) {
                // 1. Global
                $query->where('target_type', 'global')
                // 2. Industry Specific
                ->orWhere(function ($q) use ($industry) {
                    $q->where('target_type', 'industry')
                      ->whereJsonContains('target_data', $industry);
                })
                // 3. Company Specific
                ->orWhere(function ($q) use ($companyId) {
                    $q->where('target_type', 'specific_companies')
                      ->whereJsonContains('target_data', (string)$companyId)
                      // Handle both string and int matching due to JSON casting quirks
                      ->orWhereJsonContains('target_data', $companyId); 
                });
            })
            ->get();

        // Format the image URL nicely if it's a banner
        $promotions->transform(function ($promo) {
            if ($promo->format === 'hero_banner' && isset($promo->format_data['image'])) {
                $data = $promo->format_data;
                $data['image_url'] = asset('storage/' . $data['image']);
                $promo->format_data = $data;
            }
            return $promo;
        });

        return response()->json([
            'promotions' => $promotions
        ]);
    }
}