<?php
namespace App\Filament\Resources\Promotions\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('internal_name')
                    ->searchable(),
                TextColumn::make('target_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'global'             => 'success',
                        'industry'           => 'warning',
                        'specific_companies' => 'danger',
                    }),
                TextColumn::make('format')
                    ->badge(),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}