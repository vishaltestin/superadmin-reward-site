<?php

namespace App\Filament\Resources\VoucherClaims;

use App\Filament\Resources\VoucherClaims\Pages\CreateVoucherClaim;
use App\Filament\Resources\VoucherClaims\Pages\EditVoucherClaim;
use App\Filament\Resources\VoucherClaims\Pages\ListVoucherClaims;
use App\Filament\Resources\VoucherClaims\Pages\ViewVoucherClaim;
use App\Filament\Resources\VoucherClaims\Schemas\VoucherClaimForm;
use App\Filament\Resources\VoucherClaims\Schemas\VoucherClaimInfolist;
use App\Filament\Resources\VoucherClaims\Tables\VoucherClaimsTable;
use App\Models\VoucherClaim;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class VoucherClaimResource extends Resource
{
    protected static ?string $model = VoucherClaim::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;
    protected static string|UnitEnum|null $navigationGroup = 'Voucher Claims';

    public static function form(Schema $schema): Schema
    {
        return VoucherClaimForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VoucherClaimInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VoucherClaimsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVoucherClaims::route('/'),
            'create' => CreateVoucherClaim::route('/create'),
            'view' => ViewVoucherClaim::route('/{record}'),
            'edit' => EditVoucherClaim::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
