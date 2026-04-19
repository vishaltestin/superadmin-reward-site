<?php

namespace App\Filament\Resources\EmailTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('event.title')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('company.name')
                    ->label('Ownership')
                    ->default('Global Master') 
                    ->badge()
                    ->color(fn ($state) => $state === 'Global Master' ? 'success' : 'gray')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('subject')
                    ->limit(30)
                    ->searchable(),

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
                //
            ])
            ->recordActions([
                EditAction::make(),
                
                // Allows you to quickly duplicate a master template
                ReplicateAction::make()
                    ->excludeAttributes(['company_id'])
                    ->label('Duplicate & Edit'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}