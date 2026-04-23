<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;

class LeadController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|min:2|max:255',
            'last_name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'company_name' => 'required|string|min:2|max:255',
            'number_of_employee' => 'required|string',
            'designation' => 'required|string|min:2|max:255',
            'department' => 'required|string|min:2|max:255',
        ]);

        $validated['status'] = 'pending';

        Lead::create($validated);

        return response()->json([
            'message' => 'Thank you! Your application has been submitted and is pending review.'
        ], 201);
    }
}