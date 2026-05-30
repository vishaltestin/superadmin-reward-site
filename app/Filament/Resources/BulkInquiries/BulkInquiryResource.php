<?php

namespace App\Filament\Resources\BulkInquiries;

use App\Filament\Resources\BulkInquiries\Pages;
use App\Filament\Resources\BulkInquiries\Schemas\BulkInquiryForm;
use App\Filament\Resources\BulkInquiries\Tables\BulkInquiriesTable;
use App\Models\Campaign;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use BackedEnum;

class BulkInquiryResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';
    protected static string|UnitEnum|null $navigationGroup = 'B2B Orders';

    protected static ?string $modelLabel = 'Bulk Inquiry';
    protected static ?string $pluralModelLabel = 'Bulk Inquiries';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('distribution_type', 'bulk');
    }

    public static function form(Schema $schema): Schema
    {
        return BulkInquiryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BulkInquiriesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBulkInquiries::route('/'),
            'edit'  => Pages\EditBulkInquiry::route('/{record}/edit'),
        ];
    }
}