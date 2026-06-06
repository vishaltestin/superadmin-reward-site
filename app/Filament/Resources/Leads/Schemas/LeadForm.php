<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Helpers\SlugHelper;
use App\Mail\AdminAccessMail;
use App\Models\Company;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(2)->schema([

                    Section::make('Applicant Details')->schema([
                        TextInput::make('first_name')->required(),
                        TextInput::make('last_name')->required(),
                        TextInput::make('email')->email()->required(),
                        TextInput::make('mobile')->tel()->required(),
                        TextInput::make('designation'),
                        TextInput::make('department'),
                    ])->columnSpan(1),

                    Section::make('Company Request')->schema([
                        TextInput::make('company_name')->required(),
                        Select::make('number_of_employee')
                            ->label('Company Size')
                            ->options([
                                '0-50'        => '0 - 50',
                                '51-200'      => '51 - 200',
                                '201-500'     => '201 - 500',
                                '501-1000'    => '501 - 1000',
                                '1001-5000'   => '1001 - 5000',
                                '5001-10000'  => '5001 - 10000',
                                '10000+'      => '10000+',
                            ])->required(),

                        Select::make('status')
                            ->options([
                                'pending'  => 'Pending',
                                'approved' => 'Approved (Converted)',
                                'rejected' => 'Rejected',
                            ])
                            ->default('pending')
                            ->required()
                            ->afterStateUpdated(function (string $state, callable $get, $record) {
                                if ($state !== 'approved' || ! $record) {
                                    return;
                                }

                                if ($record->getOriginal('status') === 'approved') {
                                    return;
                                }

                                try {
                                    $tempPassword = Str::random(8);

                                    DB::transaction(function () use ($record, $get, $tempPassword) {
                                        $company = Company::create([
                                            'name'               => $get('company_name'),
                                            'alias'              => SlugHelper::generateUniqueSlug(
                                                                        Company::class,
                                                                        $get('company_name'),
                                                                        'alias'
                                                                    ),
                                            'number_of_employee' => $record->number_of_employee,
                                            'is_approved'        => true,
                                            'is_active'          => true,
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

                                        $loginUrl = rtrim(config('app.admin_url'), '/') . '/login';

                                        Mail::to($user->email)->send(
                                            new AdminAccessMail($user, $tempPassword, $loginUrl, $loginUrl)
                                        );
                                    });

                                    Notification::make()
                                        ->success()
                                        ->title('B2B Client Onboarded!')
                                        ->body("Company created and welcome email sent to **{$record->email}**.")
                                        ->persistent()
                                        ->send();

                                } catch (\Exception $e) {
                                    Notification::make()
                                        ->danger()
                                        ->title('Onboarding Failed')
                                        ->body($e->getMessage())
                                        ->persistent()
                                        ->send();
                                }
                            })
                            ->live(), 
                    ])->columnSpan(1),

                ]),
            ]);
    }
}