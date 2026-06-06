<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Helpers\SlugHelper;
use App\Mail\AdminAccessMail;
use App\Models\Company;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $statusInDb   = $record->getRawOriginal('status'); 
        $statusInForm = $data['status'];                  

        Log::info('[LeadOnboarding] handleRecordUpdate fired', [
            'lead_id'       => $record->id,
            'status_in_db'  => $statusInDb,
            'status_in_form'=> $statusInForm,
        ]);

        $record->update($data);

        if ($statusInForm !== 'approved' || $statusInDb === 'approved') {
            Log::info('[LeadOnboarding] Skipping — not a fresh approval', [
                'status_in_db'   => $statusInDb,
                'status_in_form' => $statusInForm,
            ]);
            return $record;
        }

        if (User::where('email', $record->email)->exists()) {
            Log::warning('[LeadOnboarding] Skipping — user already exists', [
                'email' => $record->email,
            ]);
            Notification::make()
                ->warning()
                ->title('Already Onboarded')
                ->body("A user with **{$record->email}** already exists.")
                ->persistent()
                ->send();
            return $record;
        }

        try {
            $tempPassword = Str::random(8);

            Log::info('[LeadOnboarding] Starting onboarding', [
                'lead_id'       => $record->id,
                'email'         => $record->email,
                'company_name'  => $record->company_name,
                'temp_password' => $tempPassword,
            ]);

            DB::transaction(function () use ($record, $tempPassword) {
                $company = Company::create([
                    'name'               => $record->company_name,
                    'alias'              => SlugHelper::generateUniqueSlug(
                                               Company::class,
                                               $record->company_name,
                                               'alias'
                                           ),
                    'number_of_employee' => $record->number_of_employee,
                    'is_approved'        => true,
                    'is_active'          => true,
                ]);

                Log::info('[LeadOnboarding] Company created', [
                    'company_id'   => $company->id,
                    'company_name' => $company->name,
                ]);

                $company->wallet()->firstOrCreate([], ['balance' => 0.00]);

                $user = User::create([
                    'company_id' => $company->id,
                    'name'       => trim($record->first_name . ' ' . $record->last_name),
                    'first_name' => $record->first_name,
                    'last_name'  => $record->last_name,
                    'email'      => $record->email,
                    'mobile'     => $record->mobile,
                    'password'   => Hash::make($tempPassword),
                    'user_type'  => 'business_head',
                    'is_active'  => true,
                ]);

                Log::info('[LeadOnboarding] User created', [
                    'user_id'       => $user->id,
                    'email'         => $user->email,
                    'temp_password' => $tempPassword, 
                ]);

                $loginUrl = rtrim(config('app.admin_url'), '/') . '/login';

                Mail::to($user->email)->send(
                    new AdminAccessMail($user, $tempPassword, $loginUrl, $loginUrl)
                );

                Log::info('[LeadOnboarding] Mail dispatched', ['email' => $user->email]);
            });

            Notification::make()
                ->success()
                ->title('B2B Client Onboarded!')
                ->body("Company created and welcome email sent to **{$record->email}**.")
                ->persistent()
                ->send();

        } catch (\Exception $e) {
            Log::error('[LeadOnboarding] Onboarding failed', [
                'lead_id' => $record->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->danger()
                ->title('Onboarding Failed')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }

        return $record;
    }
}