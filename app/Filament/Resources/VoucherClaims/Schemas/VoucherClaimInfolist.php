<?php

namespace App\Filament\Resources\VoucherClaims\Schemas;

use App\Models\VoucherClaim;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class VoucherClaimInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('product.name')
                    ->label('Product'),
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('claimed_at')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (VoucherClaim $record): bool => $record->trashed()),
            ]);
    }
}
