<?php
namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class StorefrontConfigController extends Controller
{
    public function initializeStore(Request $request, $slug)
    {
        $company = Company::where('alias', $slug)
            ->where('is_active', true)
            ->where('is_approved', true)
            ->first();

        if (! $company) {
            return response()->json([
                'message' => 'Marketplace space not found or temporarily suspended.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'company_id'       => $company->id,
                'name'             => $company->name,
                'alias'            => $company->alias,
                'logo_url'         => $company->logo ? asset('storage/' . $company->logo) : null,
                'industry'         => $company->industry,
                'points_name'      => $company->points_name ?? 'Points',
                'point_multiplier' => (float) ($company->point_multiplier ?? 1.00),
                'social_links'     => empty($company->social_links) ? (object) [] : $company->social_links,
                'terms_text'       => $company->terms_text,
                'privacy_text'     => $company->privacy_text,
            ],
        ]);
    }
}
