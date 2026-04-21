<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Models\Company;
use App\Models\User;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->weight('bold')->searchable(),
                
                TextColumn::make('full_name')
                    ->label('Applicant Name')
                    ->getStateUsing(fn ($record) => "{$record->first_name} {$record->last_name}"),
                
                TextColumn::make('email')->copyable()->searchable(),
                
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'), 
            ])
            ->actions([
                // THE ONBOARDING ENGINE
                Action::make('approve')
                    ->label('Approve & Onboard')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->form([
                        Grid::make(1)->schema([
                            TextInput::make('company_name')
                                ->label('Final Company Name')
                                ->default(fn ($record) => $record->company_name)
                                ->required(),
                                
                            TextInput::make('business_head_email')
                                ->label('Business Head Login Email')
                                ->default(fn ($record) => $record->email)
                                ->email()
                                
                                ->unique(User::class, 'email', ignoreRecord: false) 
                                ->required()
                                ->helperText('We will send the welcome email and password setup to this address.'),
                        ])
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            // FIX 2: Generate a readable 8-character password for testing
                            $tempPassword = \Illuminate\Support\Str::random(8); 

                            DB::transaction(function () use ($record, $data, $tempPassword) {
                                
                                // 1. Create the Company
                                $company = Company::create([
                                    'name' => $data['company_name'],
                                    'alias' => \App\Helpers\SlugHelper::generateUniqueSlug(Company::class, $data['company_name'], 'alias'),
                                    'number_of_employee' => $record->number_of_employee,
                                    'is_approved' => true, 
                                    'is_active' => true,
                                ]);

                                // 2. Trigger wallet creation manually
                                $company->wallet()->firstOrCreate([], ['balance' => 0.00]);

                                // 3. Create the Business Head
                                User::create([
                                    'company_id' => $company->id,
                                    'name' => trim($record->first_name . ' ' . $record->last_name), 
                                    'first_name' => $record->first_name,
                                    'last_name' => $record->last_name,
                                    'email' => $data['business_head_email'], 
                                    'mobile' => $record->mobile,
                                    'password' => \Illuminate\Support\Facades\Hash::make($tempPassword),
                                    'user_type' => 'business_head',
                                    'is_active' => true,
                                ]);

                                // 4. Mark Lead as approved
                                $record->update(['status' => 'approved']);
                            });
                            
                            // FIX 3: Show the password in the notification and make it stick to the screen!
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('B2B Client Onboarded!')
                                ->body("The company has been created.\n\n**Login:** {$data['business_head_email']}\n**Password:** {$tempPassword}")
                                ->persistent() // Makes the notification stay open so you can copy it
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Onboarding Failed')
                                ->body($e->getMessage()) 
                                ->persistent()
                                ->send();
                        }
                    }),
                    
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['status' => 'rejected'])),

                EditAction::make()->iconButton(),
            ]);
    }
}