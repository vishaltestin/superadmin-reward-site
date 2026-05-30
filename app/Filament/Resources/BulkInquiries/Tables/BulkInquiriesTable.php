<?php

namespace App\Filament\Resources\BulkInquiries\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BulkInquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->label('Client Company')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Campaign / Inquiry Name')
                    ->searchable(),

                TextColumn::make('total_recipients')
                    ->label('Target Headcount')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'inquiry_pending' => 'warning',
                        'active'          => 'primary',
                        'completed'       => 'success',
                        'cancelled'       => 'danger',
                        default           => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => strtoupper(str_replace('_', ' ', $state))),

                TextColumn::make('created_at')
                    ->label('Requested On')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'inquiry_pending' => 'Pending Review',
                        'active'          => 'Approved / Processing',
                        'completed'       => 'Completed',
                        'cancelled'       => 'Cancelled',
                    ])
                    ->default('inquiry_pending'),
            ])
            ->actions([
                EditAction::make()->label('Manage'),
            ]);
    }
}