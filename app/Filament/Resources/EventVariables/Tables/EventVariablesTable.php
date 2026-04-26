<?php

namespace App\Filament\Resources\EventVariables\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventVariablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event.title')
                    ->label('Event')
                    ->default('Global')
                    ->searchable(),
                
                TextColumn::make('name')
                    ->searchable(),
                
                TextColumn::make('value')
                    ->searchable(),
                
                // NEW COLUMN: Usage Type Badge
                TextColumn::make('usage_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'email' => 'info',
                        'landing_page' => 'success',
                        'both' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'email' => 'Email',
                        'landing_page' => 'Landing Page',
                        'both' => 'Both',
                        default => ucfirst($state),
                    }),

                IconColumn::make('is_active')
                    ->boolean(),
                
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // You could easily add a SelectFilter here later for usage_type!
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