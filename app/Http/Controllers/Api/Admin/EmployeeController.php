<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vertical;
use App\Models\RewardeeProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EmployeeController extends Controller
{
    /**
     * Centralized security check to ensure the user is allowed to manage this vertical.
     */
    private function authorizeVerticalAccess(User $user, string $verticalSlug): Vertical
    {
        $vertical = Vertical::where('slug', $verticalSlug)->firstOrFail();

        if ($user->user_type === 'sub_admin') {
            $hasAccess = $user->managedVerticals()->where('vertical_id', $vertical->id)->exists();
            if (!$hasAccess) {
                throw new AccessDeniedHttpException('You do not have permission to manage this vertical.');
            }
        }

        return $vertical;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $verticalSlug = $request->query('vertical', 'internal');
        
        $this->authorizeVerticalAccess($user, $verticalSlug);

        $searchTerm = $request->query('search', '');

        $query = User::where('company_id', $user->company_id)
            ->where('user_type', 'rewardee')
            ->whereHas('rewardeeProfile.vertical', function ($q) use ($verticalSlug) {
                $q->where('slug', $verticalSlug);
            })
            ->with('rewardeeProfile');

        if (!empty($searchTerm)) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('mobile', 'LIKE', "%{$searchTerm}%");
            });
        }

        $employees = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($employees);
    }

    public function store(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'vertical_slug' => 'required|string|exists:verticals,slug',
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'mobile'        => 'required|string|max:20',
            'custom_data'   => 'nullable|array',
        ]);

        $vertical = $this->authorizeVerticalAccess($admin, $validated['vertical_slug']);

        $user = DB::transaction(function () use ($validated, $admin, $vertical) {
            $newUser = new User();
            $newUser->fill([
                'company_id' => $admin->company_id,
                'user_type'  => 'rewardee',
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'email'      => $validated['email'],
                'mobile'     => $validated['mobile'],
                'password'   => Hash::make(Str::random(12)), 
                'is_active'  => true,
            ]);
            $newUser->save();

            RewardeeProfile::create([
                'user_id'       => $newUser->id,
                'company_id'    => $admin->company_id,
                'vertical_id'   => $vertical->id,
                'vertical_data' => $validated['custom_data'] ?? [], 
            ]);

            return $newUser;
        });

        return response()->json([
            'message' => 'Recipient added successfully.',
            'data'    => $user
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $admin = $request->user();

        $targetUser = User::where('company_id', $admin->company_id)
            ->where('user_type', 'rewardee')
            ->with('rewardeeProfile.vertical')
            ->findOrFail($id);

        $currentVerticalSlug = $targetUser->rewardeeProfile->vertical->slug;

        $this->authorizeVerticalAccess($admin, $currentVerticalSlug);

        $validated = $request->validate([
            'first_name'  => 'required|string|max:255',
            'last_name'   => 'required|string|max:255',
            'email'       => ['required', 'email', Rule::unique('users')->ignore($targetUser->id)],
            'mobile'      => 'required|string|max:20',
            'custom_data' => 'nullable|array',
        ]);

        DB::transaction(function () use ($validated, $targetUser) {
            $targetUser->update([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'email'      => $validated['email'],
                'mobile'     => $validated['mobile'],
            ]);

            if (isset($validated['custom_data'])) {
                $existingData = $targetUser->rewardeeProfile->vertical_data ?? [];
                $targetUser->rewardeeProfile->update([
                    'vertical_data' => array_merge($existingData, $validated['custom_data'])
                ]);
            }
        });

        return response()->json([
            'message' => 'Recipient updated successfully.'
        ]);
    }

    public function bulkUpload(Request $request)
    {
        $admin = $request->user();

        $request->validate([
            'vertical_slug' => 'required|string|exists:verticals,slug',
            'file'          => 'required|file|mimes:csv,txt|max:5120', 
        ]);

       $vertical = $this->authorizeVerticalAccess($admin, $request->vertical_slug);

        $file = $request->file('file');
        
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle); 
        
        if (!$header) {
            return response()->json(['message' => 'Invalid or empty CSV file.'], 400);
        }

        $coreColumns = ['first_name', 'last_name', 'email', 'mobile'];
        $successCount = 0;
        $errors = [];
        $rowNumber = 1;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                
                if (array_filter($row) === []) {
                    continue; 
                }

                $rowData = array_combine($header, $row);
                
                if (empty($rowData['first_name']) || empty($rowData['last_name']) || empty($rowData['email'])) {
                    $errors[] = "Row {$rowNumber}: Missing first name, last name, or email.";
                    continue;
                }

                if (User::where('email', $rowData['email'])->exists()) {
                    $errors[] = "Row {$rowNumber}: Email '{$rowData['email']}' already exists.";
                    continue;
                }

                $customData = [];
                foreach ($rowData as $column => $value) {
                    if (!in_array($column, $coreColumns)) {
                        $customData[$column] = $value;
                    }
                }

                $newUser = new User();
                $newUser->fill([
                    'company_id' => $admin->company_id,
                    'user_type'  => 'rewardee',
                    'first_name' => $rowData['first_name'],
                    'last_name'  => $rowData['last_name'],
                    'email'      => $rowData['email'],
                    'mobile'     => $rowData['mobile'] ?? null,
                    'password'   => Hash::make(Str::random(12)), 
                    'is_active'  => true,
                ]);
                $newUser->save();

                RewardeeProfile::create([
                    'user_id'       => $newUser->id,
                    'company_id'    => $admin->company_id,
                    'vertical_id'   => $vertical->id,
                    'vertical_data' => $customData, 
                ]);

                $successCount++;
            }

            fclose($handle);

            if (count($errors) > 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Upload failed due to data errors.',
                    'errors'  => array_slice($errors, 0, 10) 
                ], 422);
            }

            DB::commit();

            return response()->json([
                'message' => "Successfully imported {$successCount} recipients."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            if (is_resource($handle)) {
                fclose($handle);
            }
            return response()->json(['message' => 'A server error occurred during import.'], 500);
        }
    }
}