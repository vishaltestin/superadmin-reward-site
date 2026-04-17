<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('last_name')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),

                TextColumn::make('company.name')
                    ->sortable()
                    ->searchable(query: function ($query, $search) {
    $query->whereHas('company', fn ($q) =>
        $q->where('name', 'like', "%{$search}%")
    );
})
                    ->badge()
                    ->color('gray'),

                TextColumn::make('user_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'business_head' => 'warning',
                        'sub_admin' => 'primary',
                        'rewardee' => 'success',
                    })
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                
                IconColumn::make('is_active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_type')
                    ->options([
                        'super_admin' => 'Super Admin',
                        'business_head' => 'Business Head',
                        'sub_admin' => 'Sub Admin',
                        'rewardee' => 'Rewardee',
                    ]),
                SelectFilter::make('company_id')
                    ->relationship('company', 'name')
                    ->label('Company')
                    ->searchable(),
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