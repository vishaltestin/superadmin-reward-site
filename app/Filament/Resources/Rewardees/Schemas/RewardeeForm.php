<?php

namespace App\Filament\Resources\Rewardees\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RewardeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
        ->columns(1)
            ->components([
                Grid::make(2)
                    ->schema([
                        Section::make('System Linking')
                            ->schema([
                                Select::make('user_id')
                                    ->relationship('user', 'email')
                                    ->disabled() // As Super Admin, you usually shouldn't change the user identity here
                                    ->label('User Email'),

                                Select::make('company_id')
                                    ->relationship('company', 'name')
                                    ->disabled()
                                    ->label('Belongs to Company'),

                                Select::make('vertical_id')
                                    ->relationship('vertical', 'name')
                                    ->disabled()
                                    ->label('Assigned Vertical'),
                            ])->columnSpanFull(),

                        Section::make('Vertical Specific Data (JSON)')
                            ->description('These fields vary based on the Vertical (e.g., Real Estate vs Auto Dealers).')
                            ->schema([
                                KeyValue::make('vertical_data')
                                    ->label('')
                                    ->reorderable(false)
                            ])->columnSpanFull(),
                    ]),
            ]);
    }
}