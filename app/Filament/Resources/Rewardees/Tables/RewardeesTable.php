<?php

namespace App\Filament\Resources\Rewardees\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RewardeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.first_name')
                    ->label('First Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.last_name')
                    ->label('Last Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('Email Address')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('company.name')
                    ->label('Company')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vertical.name')
                    ->label('Vertical')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('created_at')
                    ->label('Added On')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->defaultGroup('vertical.name') 
            ->filters([
                SelectFilter::make('company_id')
                    ->relationship('company', 'name')
                    ->label('Filter by Company')
                    ->searchable(),
                    
                SelectFilter::make('vertical_id')
                    ->relationship('vertical', 'name')
                    ->label('Filter by Vertical'),
            ])
            ->recordActions([
                EditAction::make(), // Edit will show you the JSON data!
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}