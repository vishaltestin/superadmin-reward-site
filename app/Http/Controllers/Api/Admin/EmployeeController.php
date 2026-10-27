<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminAccessMail;
use App\Mail\WelcomeEmployeeMail;
use App\Models\RewardeeProfile;
use App\Models\User;
use App\Models\Vertical;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EmployeeController extends Controller
{
    private function authorizeVerticalAccess(User $user, string $verticalSlug): Vertical
    {
        $vertical = Vertical::where('slug', $verticalSlug)->firstOrFail();

        if ($user->user_type === 'sub_admin') {
            $hasAccess = $user->managedVerticals()
                ->where('vertical_id', $vertical->id)
                ->exists();

            if (! $hasAccess) {
                throw new AccessDeniedHttpException('You do not have permission to manage this vertical.');
            }
        }

        return $vertical;
    }

    public function index(Request $request)
    {
        $user         = $request->user();
        $verticalSlug = $request->query('vertical', 'internal');

        $this->authorizeVerticalAccess($user, $verticalSlug);

        $searchTerm = trim((string) $request->query('search', ''));

        // Promoted employees remain associated with their original vertical
        // through rewardeeProfile, even after their user_type becomes sub_admin.
        $query = User::where('company_id', $user->company_id)
            ->whereIn('user_type', ['rewardee', 'sub_admin'])
            ->whereHas('rewardeeProfile.vertical', function ($query) use ($verticalSlug) {
                $query->where('slug', $verticalSlug);
            })
            ->with('rewardeeProfile')
            ->when($request->boolean('active_only'), function ($query) {
                // Campaign recipient pickers request only eligible users. The
                // regular employee-management list still shows deactivated
                // accounts for administrative purposes.
                $query->where('is_active', true);
            });

        if ($searchTerm !== '') {
            $query->where(function ($query) use ($searchTerm) {
                $query->where('first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('mobile', 'LIKE', "%{$searchTerm}%");
            });
        }

        return response()->json(
            $query->orderBy('created_at', 'desc')->paginate(10)
        );
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

        $vertical    = $this->authorizeVerticalAccess($admin, $validated['vertical_slug']);
        $rawPassword = Str::random(10);

        $user = DB::transaction(function () use ($validated, $admin, $vertical, $rawPassword) {
            $newUser = new User();
            $newUser->fill([
                'company_id' => $admin->company_id,
                'user_type'  => 'rewardee',
                'name'       => trim($validated['first_name'] . ' ' . $validated['last_name']),
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'email'      => $validated['email'],
                'mobile'     => $validated['mobile'],
                'password'   => Hash::make($rawPassword),
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

        $companySlug   = $admin->company->alias ?? Str::slug($admin->company->name);
        $storefrontUrl = rtrim(config('app.storefront_url'), '/') . "/marketplace/{$companySlug}";

        Mail::to($user->email)->send(
            new WelcomeEmployeeMail($user, $rawPassword, $storefrontUrl)
        );

        return response()->json([
            'message' => 'Recipient added successfully and welcome email dispatched.',
            'data'    => $user,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $admin = $request->user();

        $targetUser = User::where('company_id', $admin->company_id)
            ->whereIn('user_type', ['rewardee', 'sub_admin'])
            ->whereHas('rewardeeProfile.vertical')
            ->with('rewardeeProfile.vertical')
            ->findOrFail($id);

        $verticalSlug = $targetUser->rewardeeProfile->vertical->slug;
        $this->authorizeVerticalAccess($admin, $verticalSlug);

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
                'name'       => trim($validated['first_name'] . ' ' . $validated['last_name']),
                'email'      => $validated['email'],
                'mobile'     => $validated['mobile'],
            ]);

            if (array_key_exists('custom_data', $validated)) {
                $existingData = $targetUser->rewardeeProfile->vertical_data ?? [];
                $targetUser->rewardeeProfile->update([
                    'vertical_data' => array_merge($existingData, $validated['custom_data'] ?? []),
                ]);
            }
        });

        return response()->json([
            'message' => 'Recipient updated successfully.',
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
        $handle   = fopen($request->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            return response()->json(['message' => 'Unable to read the CSV file.'], 400);
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);

            return response()->json([
                'message' => 'Invalid or empty CSV file.',
                'errors'  => ['The uploaded CSV does not contain a header row.'],
            ], 400);
        }

        $header = array_map(function ($column) {
            $column = preg_replace('/^\xEF\xBB\xBF/', '', (string) $column);
            return trim($column);
        }, $header);

        if (count($header) !== count(array_unique($header))) {
            fclose($handle);

            return response()->json([
                'message' => 'The CSV contains duplicate column names.',
                'errors'  => ['Please download a fresh template and do not duplicate header columns.'],
            ], 422);
        }

        $requiredHeaders = ['first_name', 'last_name', 'email'];
        $missingHeaders  = array_values(array_diff($requiredHeaders, $header));

        if ($missingHeaders !== []) {
            fclose($handle);

            return response()->json([
                'message' => 'The CSV is missing required columns.',
                'errors'  => ['Missing required header(s): ' . implode(', ', $missingHeaders) . '.'],
            ], 422);
        }

        $coreColumns  = ['first_name', 'last_name', 'email', 'mobile'];
        $successCount = 0;
        $errors       = [];
        $rowNumber    = 1;
        $usersToEmail = [];

        $companySlug   = $admin->company->alias ?? Str::slug($admin->company->name);
        $storefrontUrl = rtrim(config('app.storefront_url'), '/') . "/marketplace/{$companySlug}";

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                $row = array_map(
                    static fn($value) => trim((string) ($value ?? '')),
                    $row,
                );

                if (count(array_filter($row, static fn($value) => $value !== '')) === 0) {
                    continue;
                }

                if (count($row) !== count($header)) {
                    $errors[] = "Row {$rowNumber}: Expected " . count($header)
                    . ' columns but found ' . count($row) . '.';
                    continue;
                }

                $rowData = array_combine($header, $row);

                if ($rowData === false) {
                    $errors[] = "Row {$rowNumber}: Could not read the row. Check the CSV columns.";
                    continue;
                }

                $firstName = trim((string) ($rowData['first_name'] ?? ''));
                $lastName  = trim((string) ($rowData['last_name'] ?? ''));
                $email     = trim((string) ($rowData['email'] ?? ''));

                if ($firstName === '' || $lastName === '' || $email === '') {
                    $errors[] = "Row {$rowNumber}: Missing first name, last name, or email.";
                    continue;
                }

                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row {$rowNumber}: '{$email}' is not a valid email address.";
                    continue;
                }

                if (User::where('email', $email)->exists()) {
                    $errors[] = "Row {$rowNumber}: Email '{$email}' already exists.";
                    continue;
                }

                $customData        = [];
                $rowHasInvalidDate = false;

                foreach ($rowData as $column => $value) {
                    if (in_array($column, $coreColumns, true)) {
                        continue;
                    }

                    $value        = trim((string) ($value ?? ''));
                    $isDateColumn = preg_match('/date|_day$/i', $column) === 1;

                    if ($isDateColumn) {
                        if ($value === '') {
                            $customData[$column] = null;
                            continue;
                        }

                        $normalizedDate = $this->normalizeCsvDate($value);

                        if ($normalizedDate === null) {
                            $errors[]          = "Row {$rowNumber}: Invalid date '{$value}' in '{$column}'. Use YYYY-MM-DD or DD/MM/YYYY.";
                            $rowHasInvalidDate = true;
                            continue;
                        }

                        $customData[$column] = $normalizedDate;
                        continue;
                    }

                    $customData[$column] = $value;
                }

                if ($rowHasInvalidDate) {
                    continue;
                }

                $rawPassword = Str::random(10);
                $newUser     = new User();
                $newUser->fill([
                    'company_id' => $admin->company_id,
                    'user_type'  => 'rewardee',
                    'first_name' => $firstName,
                    'name'       => trim($firstName . ' ' . $lastName),
                    'last_name'  => $lastName,
                    'email'      => $email,
                    'mobile'     => ($rowData['mobile'] ?? '') !== '' ? $rowData['mobile'] : null,
                    'password'   => Hash::make($rawPassword),
                    'is_active'  => true,
                ]);
                $newUser->save();

                RewardeeProfile::create([
                    'user_id'       => $newUser->id,
                    'company_id'    => $admin->company_id,
                    'vertical_id'   => $vertical->id,
                    'vertical_data' => $customData,
                ]);

                $usersToEmail[] = [
                    'user'        => $newUser,
                    'rawPassword' => $rawPassword,
                ];

                $successCount++;
            }

            fclose($handle);

            if ($errors !== []) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Upload failed due to data errors.',
                    'errors'  => array_slice($errors, 0, 10),
                ], 422);
            }

            DB::commit();

            foreach ($usersToEmail as $data) {
                Mail::to($data['user']->email)->send(
                    new WelcomeEmployeeMail($data['user'], $data['rawPassword'], $storefrontUrl)
                );
            }

            return response()->json([
                'message' => "Successfully imported {$successCount} recipients and dispatched welcome emails.",
            ]);
        } catch (\Throwable $exception) {
            DB::rollBack();

            if (is_resource($handle)) {
                fclose($handle);
            }

            report($exception);

            return response()->json([
                'message' => 'A server error occurred during import.',
            ], 500);
        }
    }

    /**
     * Convert supported CSV date formats to an unambiguous YYYY-MM-DD value.
     * Excel serial dates are accepted as well. Ambiguous slash dates use the
     * Indian DD/MM/YYYY convention unless that interpretation is impossible.
     */
    private function normalizeCsvDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]/', $value) === 1) {
            $value = substr($value, 0, 10);
        }

        if (preg_match('/^\d{5,6}(?:\.\d+)?$/', $value) === 1) {
            $serial = (int) floor((float) $value);

            if ($serial >= 15000 && $serial <= 100000) {
                return (new \DateTimeImmutable('1899-12-30'))
                    ->modify("+{$serial} days")
                    ->format('Y-m-d');
            }
        }

        $formats = [
            'Y-m-d',
            'Y/m/d',
            'd/m/Y',
            'd-m-Y',
            'd.m.Y',
            'd/m/y',
            'd-m-y',
            'd.m.y',
            'd-M-Y',
            'j-M-Y',
            'd M Y',
            'j M Y',
            'M d, Y',
            'M j, Y',
            'm/d/Y',
            'm-d-Y',
            'm.d.Y',
        ];

        foreach ($formats as $format) {
            $date        = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            $parseErrors = \DateTimeImmutable::getLastErrors();

            if (
                $date !== false
                && ($parseErrors === false
                    || ($parseErrors['warning_count'] === 0 && $parseErrors['error_count'] === 0))
            ) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    public function promoteToAdmin(Request $request, $id)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'managed_vertical_ids'   => 'required|array|min:1',
            'managed_vertical_ids.*' => 'exists:verticals,id',
        ]);

        $companyVerticalIds = $admin->company->verticals()->pluck('verticals.id')->toArray();

        if (! empty(array_diff($validated['managed_vertical_ids'], $companyVerticalIds))) {
            return response()->json([
                'message' => 'Selected verticals are not part of your company.',
            ], 422);
        }

        $employee = User::where('company_id', $admin->company_id)
            ->where('user_type', 'rewardee')
            ->findOrFail($id);

        $rawPassword = Str::random(10);

        DB::transaction(function () use ($employee, $validated, $rawPassword) {
            $employee->update([
                'user_type' => 'sub_admin',
                'password'  => Hash::make($rawPassword),
            ]);

            $employee->managedVerticals()->sync($validated['managed_vertical_ids']);
        });

        $adminLoginUrl = rtrim(config('app.admin_url'), '/') . '/login';

        Mail::to($employee->email)->send(
            new AdminAccessMail($employee, $rawPassword, $adminLoginUrl, $adminLoginUrl)
        );

        return response()->json([
            'message' => 'Employee successfully promoted to Sub-Admin and notified via email.',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $admin = $request->user();

        $targetUser = User::where('company_id', $admin->company_id)
            ->where('user_type', 'rewardee')
            ->with('rewardeeProfile.vertical')
            ->findOrFail($id);

        if ($targetUser->rewardeeProfile && $targetUser->rewardeeProfile->vertical) {
            $this->authorizeVerticalAccess(
                $admin,
                $targetUser->rewardeeProfile->vertical->slug,
            );
        }

        $hasOrders        = $targetUser->orders()->exists();
        $hasWalletHistory = $targetUser->wallet
        && $targetUser->wallet->transactions()->exists();

        if ($hasOrders || $hasWalletHistory) {
            $targetUser->update(['is_active' => false]);

            return response()->json([
                'message' => 'This member has order or wallet history, so their account was deactivated instead of deleted. They can no longer log in or receive rewards.',
            ]);
        }

        DB::transaction(function () use ($targetUser) {
            $targetUser->wallet?->delete();
            $targetUser->delete();
        });

        return response()->json([
            'message' => 'Recipient deleted successfully.',
        ]);
    }
}
