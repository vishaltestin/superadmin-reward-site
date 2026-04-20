<?php

namespace App\Filament\Resources\Verticals;

use App\Filament\Resources\Verticals\Pages\CreateVertical;
use App\Filament\Resources\Verticals\Pages\EditVertical;
use App\Filament\Resources\Verticals\Pages\ListVerticals;
use App\Filament\Resources\Verticals\Schemas\VerticalForm;
use App\Filament\Resources\Verticals\Tables\VerticalsTable;
use App\Models\Vertical;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class VerticalResource extends Resource
{
    protected static ?string $model = Vertical::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $recordTitleAttribute = 'name';
    protected static string|UnitEnum|null $navigationGroup = 'Platform Foundation';

    public static function form(Schema $schema): Schema
    {
        return VerticalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VerticalsTable::configure($table);
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
            'index' => ListVerticals::route('/'),
            'create' => CreateVertical::route('/create'),
            'edit' => EditVertical::route('/{record}/edit'),
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
