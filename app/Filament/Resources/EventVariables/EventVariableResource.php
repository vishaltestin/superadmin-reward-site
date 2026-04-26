<?php

namespace App\Filament\Resources\EventVariables;

use App\Filament\Resources\EventVariables\Pages\CreateEventVariable;
use App\Filament\Resources\EventVariables\Pages\EditEventVariable;
use App\Filament\Resources\EventVariables\Pages\ListEventVariables;
use App\Filament\Resources\EventVariables\Schemas\EventVariableForm;
use App\Filament\Resources\EventVariables\Tables\EventVariablesTable;
use App\Models\EventVariable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EventVariableResource extends Resource
{
    protected static ?string $model = EventVariable::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';
    protected static string|UnitEnum|null $navigationGroup = 'Templates';

    public static function form(Schema $schema): Schema
    {
        return EventVariableForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventVariablesTable::configure($table);
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
            'index' => ListEventVariables::route('/'),
            'create' => CreateEventVariable::route('/create'),
            'edit' => EditEventVariable::route('/{record}/edit'),
        ];
    }
}
