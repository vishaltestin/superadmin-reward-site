<?php
namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ExperienceEnquiry;
use App\Models\Product;
use Illuminate\Http\Request;

class ExperienceEnquiryController extends Controller
{

    public function submit(Request $request)
    {
        $productSlug = $request->route('productSlug');
        $slug        = $request->route('slug');

        $product = Product::where('slug', $productSlug)
            ->where('is_active', true)
            ->firstOrFail();

        if ($product->type !== 'experience') {
            return response()->json([
                'message' => 'Invalid experience.',
            ], 422);
        }

        $validated = $request->validate([
            'guest_count'    => 'nullable|integer|min:1',
            'preferred_date' => 'nullable|date|after_or_equal:today',
            'message'        => 'nullable|string|max:1000',
        ]);

        $existingEnquiry = ExperienceEnquiry::where('product_id', $product->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($existingEnquiry) {
            return response()->json([
                'message' => 'You have already submitted an enquiry for this experience.',
            ], 409);
        }

        ExperienceEnquiry::create([
            'product_id' => $product->id,
            'user_id'    => $request->user()->id,
            ...$validated,
            'status'     => 'new',
        ]);

        return response()->json([
            'message' => 'Enquiry submitted successfully.',
        ]);
    }
    public function myEnquiries(Request $request)
    {
        return ExperienceEnquiry::with('product')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();
    }
}
