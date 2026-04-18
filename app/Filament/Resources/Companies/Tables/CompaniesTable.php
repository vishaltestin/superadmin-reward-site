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

class CompaniesTable
{
    public static function configure(Table $table) : Table
    {
        return $table->columns([
            ImageColumn::make('logo')
                ->circular()
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
                // Hide this button if the company isn't approved yet (since they have no wallet)
                ->visible(fn ($record) => $record->is_approved)
                ->form([
                    Select::make('transaction_type')
                        ->label('Action')
                        ->options([
                            'credit' => 'Add Funds (Credit)',
                            'debit'  => 'Deduct Funds (Debit)',
                        ])
                        ->required(),
                    TextInput::make('amount')
                        ->label('Amount (₹)')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('description')
                        ->label('Description / Reference')
                        ->placeholder('e.g., Bank Transfer INV-123')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function ($record, array $data) {
                    // Fallback to ensure wallet exists just in case
                    $wallet = $record->wallet()->firstOrCreate([], ['balance' => 0.00]);

                    try {
                        if ($data['transaction_type'] === 'credit') {
                            $wallet->credit((float) $data['amount'], $data['description']);
                            $message = 'Funds successfully credited to ledger.';
                        } else {
                            $wallet->debit((float) $data['amount'], $data['description']);
                            $message = 'Funds successfully deducted from ledger.';
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
                            ->body($e->getMessage()) // e.g., "Insufficient funds" from your Wallet model
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