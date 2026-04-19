<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Models\Company;
use App\Models\User;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            
            // 1. Identifying the Owner (Polymorphic Magic)
            TextColumn::make('owner')
                ->label('Account Owner')
                ->state(function ($record) {
                    $owner = $record->wallet->walletable;
                    if ($owner instanceof Company) {
                        return $owner->name; // Company Name
                    }
                    if ($owner instanceof User) {
                        return trim($owner->first_name . ' ' . $owner->last_name); // User Name
                    }
                    return 'Unknown';
                })
                ->description(function ($record) {
                    // Show a tiny sub-label telling us if this is a Company or User
                    $type = class_basename($record->wallet->walletable_type);
                    return "Account Type: {$type}";
                })
                ->searchable(query: function (Builder $query, string $search) {
                    // Complex search across both relationships
                    $query->whereHasMorph('wallet.walletable', [Company::class, User::class], function (Builder $query, string $type) use ($search) {
                        if ($type === Company::class) {
                            $query->where('name', 'like', "%{$search}%");
                        } elseif ($type === User::class) {
                            $query->where('first_name', 'like', "%{$search}%")
                                  ->orWhere('last_name', 'like', "%{$search}%");
                        }
                    });
                }),

            // 2. Transaction Type (Credit vs Debit)
            TextColumn::make('type')
                ->label('Type')
                ->badge()
                ->formatStateUsing(fn (string $state): string => ucfirst($state))
                ->color(fn (string $state): string => match ($state) {
                    'credit' => 'success', // Green for incoming
                    'debit' => 'danger',   // Red for outgoing
                    default => 'gray',
                }),

            // 3. Amount
            TextColumn::make('amount')
                ->label('Amount')
                ->money('INR')
                ->weight('bold')
                ->sortable()
                ->color(fn ($record) => $record->type === 'credit' ? 'success' : 'danger'),

            // 4. Description/Reason
            TextColumn::make('description')
                ->label('Description')
                ->wrap()
                ->searchable(),

            // 5. Timestamp
            TextColumn::make('created_at')
                ->label('Date & Time')
                ->dateTime('M j, Y - g:i A')
                ->sortable(),

        ])
        ->filters([
            // Filter by Credit or Debit
            SelectFilter::make('type')
                ->options([
                    'credit' => 'Credit (Incoming)',
                    'debit' => 'Debit (Outgoing)',
                ]),
                
            // Filter by Account Type (Company vs User)
            SelectFilter::make('account_type')
                ->label('Account Type')
                ->options([
                    Company::class => 'Companies Only',
                    User::class => 'Users/Employees Only',
                ])
                ->query(function (Builder $query, array $data) {
                    if (!empty($data['value'])) {
                        $query->whereHas('wallet', function (Builder $walletQuery) use ($data) {
                            $walletQuery->where('walletable_type', $data['value']);
                        });
                    }
                }),
        ])
        ->actions([
            // Intentionally NO Edit or Delete actions to preserve ledger integrity.
        ])
        ->bulkActions([
            // Intentionally NO bulk delete actions.
        ])
        // Optional: Remove the default click-to-edit behavior
        ->recordUrl(null);
    }
}