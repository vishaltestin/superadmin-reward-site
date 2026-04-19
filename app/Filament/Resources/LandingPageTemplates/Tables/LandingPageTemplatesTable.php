<?php

namespace App\Filament\Resources\LandingPageTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;

class LandingPageTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->label('Internal Name'),

                TextColumn::make('title')
                    ->searchable()
                    ->limit(20)
                    ->label('Public Title'),

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

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'published' => 'success',
                        'archived' => 'danger',
                    }),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

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
                ReplicateAction::make()
                    ->excludeAttributes(['company_id'])
                    ->label('Duplicate Master'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}