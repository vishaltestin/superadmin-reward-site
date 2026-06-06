<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Mail\LeadSubmittedMail;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LeadController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'         => 'required|string|min:2|max:255',
            'last_name'          => 'required|string|min:2|max:255',
            'email'              => 'required|email|max:255',
            'mobile'             => 'required|string|max:20',
            'company_name'       => 'required|string|min:2|max:255',
            'number_of_employee' => 'required|string',
            'designation'        => 'required|string|min:2|max:255',
            'department'         => 'required|string|min:2|max:255',
        ]);

        $validated['status'] = 'pending';

        $lead = Lead::create($validated);

        Mail::to($lead->email)->send(new LeadSubmittedMail($lead));

        return response()->json([
            'message' => 'Thank you! Your application has been submitted and is pending review.'
        ], 201);
    }
}