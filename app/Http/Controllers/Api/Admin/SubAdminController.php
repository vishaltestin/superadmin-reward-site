<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminAccessMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SubAdminController extends Controller
{
    public function index(Request $request)
    {
        $subAdmins = User::where('company_id', $request->user()->company_id)
            ->where('user_type', 'sub_admin')
            ->with('managedVerticals:id,name,slug')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $subAdmins]);
    }

    public function store(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'first_name'             => 'required|string|max:255',
            'last_name'              => 'required|string|max:255',
            'email'                  => 'required|email|unique:users,email',
            'mobile'                 => 'nullable|string|max:20',
            'managed_vertical_ids'   => 'required|array|min:1',
            'managed_vertical_ids.*' => 'exists:verticals,id',
        ]);

        $rawPassword = Str::random(10); 

        $subAdmin = DB::transaction(function () use ($validated, $admin, $rawPassword) {
            $newAdmin = User::create([
                'company_id' => $admin->company_id,
                'user_type'  => 'sub_admin',
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'name'       => $validated['first_name'] . ' ' . $validated['last_name'],
                'email'      => $validated['email'],
                'mobile'     => $validated['mobile'],
                'password'   => Hash::make($rawPassword),
                'is_active'  => true,
            ]);

            $newAdmin->managedVerticals()->sync($validated['managed_vertical_ids']);

            return $newAdmin;
        });

        $adminLoginUrl = rtrim(config('app.admin_url'), '/') . '/login';

        Mail::to($subAdmin->email)->send(
            new AdminAccessMail($subAdmin, $rawPassword, $adminLoginUrl, $adminLoginUrl)
        );

        return response()->json(['message' => 'Sub-Admin created successfully.'], 201);
    }

    public function update(Request $request, $id)
    {
        $admin = $request->user();

        $subAdmin = User::where('company_id', $admin->company_id)
            ->where('user_type', 'sub_admin')
            ->findOrFail($id);

        $validated = $request->validate([
            'first_name'             => 'required|string|max:255',
            'last_name'              => 'required|string|max:255',
            'email'                  => ['required', 'email', Rule::unique('users')->ignore($subAdmin->id)],
            'mobile'                 => 'nullable|string|max:20',
            'managed_vertical_ids'   => 'required|array|min:1',
            'managed_vertical_ids.*' => 'exists:verticals,id',
        ]);

        DB::transaction(function () use ($validated, $subAdmin) {
            $subAdmin->update([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'email'      => $validated['email'],
                'mobile'     => $validated['mobile'],
            ]);

            $subAdmin->managedVerticals()->sync($validated['managed_vertical_ids']);
        });

        return response()->json(['message' => 'Sub-Admin updated successfully.']);
    }

    public function destroy(Request $request, $id)
    {
        $admin = $request->user();

        $subAdmin = User::where('company_id', $admin->company_id)
            ->where('user_type', 'sub_admin')
            ->findOrFail($id);

        $subAdmin->delete();

        return response()->json(['message' => 'Sub-Admin deleted successfully.']);
    }
}
