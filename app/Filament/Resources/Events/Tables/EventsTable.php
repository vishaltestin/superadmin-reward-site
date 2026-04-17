<?php

namespace App\Filament\Resources\Events\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('vertical.name')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                   ->searchable(query: function ($query, $search) {
    $query->whereHas('vertical', fn ($q) =>
        $q->where('name', 'like', "%{$search}%")
    );
}),

                TextColumn::make('parent.title')
                    ->label('Group')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultGroup('vertical.name') // Automatically groups the rows by Vertical
            ->filters([
                SelectFilter::make('vertical_id')
                    ->relationship('vertical', 'name')
                    ->label('Filter by Vertical'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}