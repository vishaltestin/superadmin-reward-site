<?php
namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Mail\DemoRequestThankYouMail;
use App\Models\DemoRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class DemoRequestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'                => ['required', 'string', 'min:2', 'max:255'],
            'last_name'                 => ['required', 'string', 'min:2', 'max:255'],
            'email'                     => ['required', 'email', 'max:255'],
            'company_name'              => ['required', 'string', 'min:2', 'max:255'],
            'mobile'                    => ['nullable', 'string', 'max:20'],
            'department'                => ['required', 'string', 'max:255'],
            'designation'               => ['required', 'string', 'min:2', 'max:255'],
            'number_of_employee'        => ['required', 'string', 'max:30'],
            'how_did_you_hear_about_us' => ['required', 'string', 'max:255'],
            'message'                   => ['nullable', 'string', 'max:1000'],
        ]);

        $demoRequest = DemoRequest::create([
             ...$validated,
            'status' => 'new',
        ]);

        Mail::to($demoRequest->email)->send(
            new DemoRequestThankYouMail($demoRequest),
        );

        return response()->json([
            'message' => 'Thank you! Your demo request has been received. Our team will contact you shortly.',
        ], 201);
    }
}
