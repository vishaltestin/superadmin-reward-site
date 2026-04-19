<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions;

class TierPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'tierPrices';

    protected static ?string $recordTitleAttribute = 'min_quantity';
    
    protected static ?string $title = 'Bulk Tier Pricing';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('min_quantity')
                    ->label('Minimum Quantity Required')
                    ->numeric()
                    ->required()
                    ->minValue(2)
                    ->helperText('The lowest number of items a user must buy to get this price.'),

                TextInput::make('selling_price')
                    ->label('Discounted Price (Per Unit)')
                    ->numeric()
                    ->required()
                    ->prefix('₹'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('min_quantity')
                    ->label('Buy At Least')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => "{$state}+ Units"),

                TextColumn::make('selling_price')
                    ->label('Price Per Unit')
                    ->money('INR')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),
            ])
            ->defaultSort('min_quantity', 'asc')
            ->filters([])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}