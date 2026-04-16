<?php

namespace App\Filament\Resources\Companies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table) : Table
    {
        return $table->columns([
            ImageColumn
                ::make('logo')
                ->circular()
                ->defaultImageUrl(url('/placeholder.png')),

            TextColumn::make('name')
                ->searchable()
                ->sortable()
                ->weight('bold')
                ->description(fn ($record) => $record->industry),

            TextColumn::make('available_funds')->money('INR')->sortable(),

            TextColumn::make('verticals.name')
                ->label('Verticals')
                ->badge()
                ->color('primary')
                ->separator(',')
                ->limitList(3),

            TextColumn::make('categories.name')
                ->badge()
                ->color('success')
                ->separator(',')
                ->limitList(3),

            IconColumn::make('is_approved')->boolean()->label('Approved'),

            IconColumn::make('is_active')->boolean()->label('Active'),

            TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
                TrashedFilter::make(),
                TernaryFilter::make('is_approved')
                    ->label('Approval Status')
                    ->boolean()
                    ->trueLabel('Approved Leads')
                    ->falseLabel('Pending Leads'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}