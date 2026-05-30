<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms\Components\Select;
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
                Select::make('product_variant_id')
                    ->label('Applies To')
                    ->options(function ($livewire) {
                        return $livewire->ownerRecord->variants->pluck('name', 'id');
                    })
                    ->placeholder('All Variants (Global Discount)')
                    ->columnSpanFull()
                    ->searchable()
                    ->preload(),

                TextInput::make('min_quantity')
                    ->label('Minimum Quantity Required')
                    ->numeric()
                    ->required()
                    ->minValue(2),

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
                TextColumn::make('variant.name')
                    ->label('Applies To')
                    ->default('All Variants')
                    ->badge()
                    ->color(fn ($state) => $state === 'All Variants' ? 'success' : 'gray'),

                TextColumn::make('min_quantity')
                    ->label('Buy At Least')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => "{$state}+ Units"),

                TextColumn::make('selling_price')
                    ->label('Price Per Unit')
                    ->money('INR')
                    ->sortable()
                    ->weight('bold'),
            ])
            ->defaultSort('min_quantity', 'asc')
            ->headerActions([Actions\CreateAction::make()])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }
}