<?php
namespace App\Filament\Resources\DemoRequests\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;          
use Filament\Actions\DeleteAction;        
use Filament\Actions\BulkActionGroup;     
use Filament\Actions\DeleteBulkAction;    

class DemoRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Prospect Name')
                    ->getStateUsing(fn ($record) => "{$record->first_name} {$record->last_name}"),
                TextColumn::make('email')
                    ->copyable()
                    ->searchable(),
                SelectColumn::make('status')
                    ->options([
                        'new' => 'New Request',
                        'contacted' => 'Contacted',
                        'demo_scheduled' => 'Demo Scheduled',
                        'closed' => 'Closed / Won',
                    ])
                    ->selectablePlaceholder(false),
                TextColumn::make('created_at')
                    ->label('Received On')
                    ->dateTime('M d, Y g:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'new' => 'New Requests',
                        'contacted' => 'Contacted',
                        'demo_scheduled' => 'Demo Scheduled',
                    ])
                    ->default('new'),
            ])
            ->actions([
                EditAction::make()->label('View Details'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}