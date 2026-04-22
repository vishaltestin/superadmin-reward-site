<?php

namespace App\Filament\Resources\DemoRequests;

use App\Filament\Resources\DemoRequests\Pages;
use App\Filament\Resources\DemoRequests\Schemas\DemoRequestForm;
use App\Filament\Resources\DemoRequests\Tables\DemoRequestsTable;
use App\Models\DemoRequest;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;

class DemoRequestResource extends Resource
{
    protected static ?string $model = DemoRequest::class;

   protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-phone-arrow-up-right';
    protected static ?string $navigationLabel = 'Demo Requests';
    protected static string|UnitEnum|null $navigationGroup = 'Access Management';

    // Disable creating them manually since they come from the website
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DemoRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DemoRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDemoRequests::route('/'),
            'edit' => Pages\EditDemoRequest::route('/{record}/edit'),
        ];
    }
}