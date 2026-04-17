<?php

namespace App\Filament\Resources\Rewardees;

use App\Filament\Resources\Rewardees\Pages;
use App\Filament\Resources\Rewardees\Schemas\RewardeeForm;
use App\Filament\Resources\Rewardees\Tables\RewardeesTable;
use App\Models\RewardeeProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;   

class RewardeeResource extends Resource
{
    protected static ?string $model = RewardeeProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Global Directory';
    
    protected static ?string $modelLabel = 'Rewardee';

    protected static string|UnitEnum|null $navigationGroup = 'Access Management';

    public static function form(Schema $schema): Schema
    {
        return RewardeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RewardeesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRewardees::route('/'),
            'create' => Pages\CreateRewardee::route('/create'),
            'edit' => Pages\EditRewardee::route('/{record}/edit'),
        ];
    }
}