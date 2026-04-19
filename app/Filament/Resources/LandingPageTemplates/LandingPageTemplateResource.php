<?php

namespace App\Filament\Resources\LandingPageTemplates;

use App\Filament\Resources\LandingPageTemplates\Pages\CreateLandingPageTemplate;
use App\Filament\Resources\LandingPageTemplates\Pages\EditLandingPageTemplate;
use App\Filament\Resources\LandingPageTemplates\Pages\ListLandingPageTemplates;
use App\Filament\Resources\LandingPageTemplates\Schemas\LandingPageTemplateForm;
use App\Filament\Resources\LandingPageTemplates\Tables\LandingPageTemplatesTable;
use App\Models\LandingPageTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LandingPageTemplateResource extends Resource
{
    protected static ?string $model = LandingPageTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Templates';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return LandingPageTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LandingPageTemplatesTable::configure($table);
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
            'index' => ListLandingPageTemplates::route('/'),
            'create' => CreateLandingPageTemplate::route('/create'),
            'edit' => EditLandingPageTemplate::route('/{record}/edit'),
        ];
    }
}
