<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;


class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('main_image')
                    ->circular()
                    ->defaultImageUrl(url('/placeholder-product.png')),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->brand?->name ?? 'No Brand'),

                TextColumn::make('primaryCategory.name')
                    ->label('Category')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('selling_price')
                    ->label('Price')
                    ->money('INR')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'physical' => 'gray',
                        'digital' => 'info',
                        'experience' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->relationship('primaryCategory', 'name')
                    ->label('Primary Category')
                    ->searchable(),
                
                SelectFilter::make('type')
                    ->options([
                        'physical' => 'Physical',
                        'digital' => 'Digital',
                        'experience' => 'Experience',
                    ]),
            ])
           ->recordActions([
                ReplicateAction::make()
                    ->excludeAttributes(['slug', 'sku']) // Don't copy unique fields
                    ->beforeReplicaSaved(function (Model $replica): void {
                        $replica->name = $replica->name . ' (Copy)';
                        $replica->is_active = false; // Put copies in draft mode safely
                    })
                    ->color('warning'),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}