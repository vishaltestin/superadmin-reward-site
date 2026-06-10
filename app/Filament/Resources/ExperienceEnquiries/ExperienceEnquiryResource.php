<?php

namespace App\Filament\Resources\ExperienceEnquiries;

use App\Filament\Resources\ExperienceEnquiries\Pages\CreateExperienceEnquiry;
use App\Filament\Resources\ExperienceEnquiries\Pages\EditExperienceEnquiry;
use App\Filament\Resources\ExperienceEnquiries\Pages\ListExperienceEnquiries;
use App\Filament\Resources\ExperienceEnquiries\Pages\ViewExperienceEnquiry;
use App\Filament\Resources\ExperienceEnquiries\Schemas\ExperienceEnquiryForm;
use App\Filament\Resources\ExperienceEnquiries\Schemas\ExperienceEnquiryInfolist;
use App\Filament\Resources\ExperienceEnquiries\Tables\ExperienceEnquiriesTable;
use App\Models\ExperienceEnquiry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ExperienceEnquiryResource extends Resource
{
    protected static ?string $model = ExperienceEnquiry::class;

   protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;
    protected static string|UnitEnum|null $navigationGroup = 'Event Enquiries';

    public static function form(Schema $schema): Schema
    {
        return ExperienceEnquiryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExperienceEnquiryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExperienceEnquiriesTable::configure($table);
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
            'index' => ListExperienceEnquiries::route('/'),
            'create' => CreateExperienceEnquiry::route('/create'),
            'view' => ViewExperienceEnquiry::route('/{record}'),
            'edit' => EditExperienceEnquiry::route('/{record}/edit'),
        ];
    }
}
