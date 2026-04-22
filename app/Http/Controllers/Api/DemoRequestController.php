<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DemoRequest;

class DemoRequestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'company_name' => 'required|string|max:255',
            'mobile' => 'nullable|string|max:20',
            'message' => 'nullable|string|max:1000',
        ]);

        $validated['status'] = 'new';

        DemoRequest::create($validated);

        // Optional: Trigger a Laravel Event here to Slack/Email your Sales Team!

        return response()->json([
            'message' => 'Demo request received! Our sales team will contact you shortly to schedule a call.'
        ], 201);
    }
}