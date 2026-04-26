<?php

namespace App\Filament\Resources\Companies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Exception;
use Filament\Schemas\Components\Utilities\Set;

class CompaniesTable
{
    public static function configure(Table $table) : Table
    {
        return $table->columns([
            ImageColumn::make('logo')
                ->circular()
                ->disk('public')
                ->defaultImageUrl(url('/placeholder.png')),

            TextColumn::make('name')
                ->searchable()
                ->sortable()
                ->weight('bold')
                ->description(fn ($record) => $record->industry),

            // Dynamically pulling the real ledger balance from the wallet relationship
            TextColumn::make('wallet.balance')
                ->label('Available Funds')
                ->money('INR')
                ->sortable()
                ->default(0.00)
                ->badge()
                ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

            TextColumn::make('verticals.name')
                ->label('Verticals')
                ->badge()
                ->color('primary')
                ->separator(',')
                ->limitList(3),

            TextColumn::make('categories.name')
                ->badge()
                ->color('success')
                ->separator(',')
                ->limitList(3),

            IconColumn::make('is_approved')->boolean()->label('Approved'),

            IconColumn::make('is_active')->boolean()->label('Active'),

            TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            TrashedFilter::make(),
            TernaryFilter::make('is_approved')
                ->label('Approval Status')
                ->boolean()
                ->trueLabel('Approved Leads')
                ->falseLabel('Pending Leads'),
        ])
        ->actions([
            // Ledger Action: Manage Funds
            Action::make('manage_funds')
                ->label('Manage Funds')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn ($record) => $record->is_approved)
                ->form([
                    Select::make('transaction_type')
                        ->label('Action')
                        ->options([
                            'credit' => 'Add Funds (Credit)',
                            'debit'  => 'Deduct Funds (Debit)',
                        ])
                        ->default('credit')
                        ->live()
                        ->required(),
                    
                    // 1. Ask for the actual money paid
                    TextInput::make('fiat_paid')
                        ->label('Actual Money Paid (₹)')
                        ->numeric()
                        ->minValue(1)
                        ->live(debounce: 500)
                        ->required()
                        // THE MAGIC: Instantly calculate the points based on the company's multiplier
                         ->afterStateUpdated(function (Set $set, ?string $state, $record) {
        if ($state && $record) {
            $calculatedPoints = (float) $state * (float) $record->point_multiplier;
            $set('amount', $calculatedPoints);
        }
    }),

                    // 2. The calculated points (Admin can still manually override if they want)
                    TextInput::make('amount')
                        ->label('Total Points to Credit/Debit')
                        ->helperText(fn ($record) => "Auto-calculated based on this company's {$record->point_multiplier}x multiplier.")
                        ->numeric()
                        ->required(),

                    TextInput::make('description')
                        ->label('Description / Reference')
                        ->placeholder('e.g., Bank Transfer INV-123')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function ($record, array $data) {
                    $wallet = $record->wallet()->firstOrCreate([], ['balance' => 0.00]);

                    $fiatPaidValue = (float) $data['fiat_paid'];
                    $fiatPaidFormatted = number_format($fiatPaidValue, 2);
                    $auditNote = "{$data['description']} (Rate: {$record->point_multiplier}x)";

                    try {
                        if ($data['transaction_type'] === 'credit') {
                            // Pass $fiatPaidValue as the 5th parameter!
                            // (amount, description, reference, expiresAt, fiatPaid)
                            $wallet->credit((float) $data['amount'], $auditNote, null, null, $fiatPaidValue);
                            $message = 'Points successfully credited to ledger.';
                        } else {
                            // Pass $fiatPaidValue as the 4th parameter!
                            // (amount, description, reference, fiatPaid)
                            $wallet->debit((float) $data['amount'], $auditNote, null, $fiatPaidValue);
                            $message = 'Points successfully deducted from ledger.';
                        }

                        Notification::make()
                            ->success()
                            ->title('Ledger Updated')
                            ->body($message)
                            ->send();
                    } catch (Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Transaction Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            EditAction::make(),
        ])
        ->toolbarActions([
            BulkActionGroup::make([
                DeleteBulkAction::make(),
                ForceDeleteBulkAction::make(),
                RestoreBulkAction::make(),
            ]),
        ]);
    }
}