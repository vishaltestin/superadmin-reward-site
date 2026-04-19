<?php

namespace App\Filament\Resources\Transactions;

use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Transactions\Tables\TransactionsTable;
use App\Models\Transaction;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    // Fix: Added \BackedEnum to match Filament's base resource signature
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-refund';
    
    // Fix: Added \UnitEnum to match Filament's base resource signature
    protected static null|string|UnitEnum $navigationGroup = 'Financial Engine';
    
    protected static ?string $navigationLabel = 'Master Ledger';

    // Disable the "Create" button globally for this resource
    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // Eager load the wallet and the polymorphic owner (User or Company)
            ->with(['wallet.walletable'])
            // Newest transactions first
            ->latest(); 
    }

    public static function getPages(): array
    {
        return [
            // Only the list page exists! No edit, no create.
            'index' => ListTransactions::route('/'),
        ];
    }
}