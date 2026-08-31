<?php
namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'product_name';

    protected static ?string $title = 'Purchased Items';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_name')
                    ->label('Item')
                    ->extraAttributes(['class' => 'font-bold underline'])
                    ->description(fn($record) => $record->variant?->name
                            ? 'Variant: ' . $record->variant->name
                            : 'Standard Item')
                    ->url(fn($record) => $record->product_id
                            ? \App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $record->product_id])
                            : null)
                    ->openUrlInNewTab(true)
                    ->color('primary'),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->money('inr'),

                TextColumn::make('total_price')
                    ->label('Line Total')
                    ->money('inr')
                    ->extraAttributes(['class' => 'font-bold'])
                    ->color('success'),

                TextColumn::make('delivery_status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'delivered' => 'success',
                        default     => 'secondary',
                    }),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}