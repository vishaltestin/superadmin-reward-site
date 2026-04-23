<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $recordTitleAttribute = 'name';

public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                \Filament\Schemas\Components\Grid::make(2)->schema([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->required()
                        ->placeholder('e.g., Red - Large'),

                    \Filament\Forms\Components\TextInput::make('sku')
                        ->required()
                        ->unique(ignoreRecord: true),

                    // --- NEW IMAGE UPLOAD FOR VARIANT ---
                    \Filament\Forms\Components\FileUpload::make('image')
                        ->label('Variant Image (Optional)')
                        ->image()
                        ->disk('public')
                        ->directory('products/variants')
                        ->columnSpanFull(), // Makes the image upload take the full width
                    // ------------------------------------

                    \Filament\Forms\Components\TextInput::make('mrp')
                        ->label('Variant MRP')
                        ->numeric()
                        ->prefix('₹'),

                    \Filament\Forms\Components\TextInput::make('selling_price')
                        ->label('Variant Selling Price')
                        ->numeric()
                        ->default(0.00)
                        ->required()
                        ->prefix('₹'),

                    \Filament\Forms\Components\TextInput::make('stock_quantity')
                        ->numeric()
                        ->default(0),

                    \Filament\Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ]),

                \Filament\Forms\Components\KeyValue::make('attributes')
                    ->label('Variant Attributes (Dynamic)')
                    ->keyLabel('Type (e.g., Size, Color)')
                    ->valueLabel('Value (e.g., XL, Blue)')
                    ->addActionLabel('Add Attribute')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                    
                TextColumn::make('selling_price')
                    ->label('Price')
                    ->money('INR')
                    ->sortable(),
                    
                TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->sortable(),
                    
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->filters([
                //
            ])
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