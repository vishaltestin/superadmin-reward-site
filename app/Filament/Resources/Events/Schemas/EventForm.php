<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\Event;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Select::make('vertical_id')
                            ->relationship('vertical', 'name')
                            ->required()
                            ->live()
                            ->label('Vertical'),

                        Select::make('parent_id')
                            ->relationship(
                                name: 'parent',
                                titleAttribute: 'title',
                                modifyQueryUsing: fn (Builder $query, Get $get) => 
                                    $query->where('vertical_id', $get('vertical_id'))
                                          ->whereNull('parent_id')
                            )
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get) => blank($get('vertical_id')))
                            ->label('Parent Group')
                            ->helperText('Select a group like "Sales" or "Marketing" if applicable.'),

                        TextInput::make('title')
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('icon')
                            ->label('Heroicon Name')
                            ->placeholder('e.g. heroicon-o-gift'),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->default(true),
                    ]),
            ]);
    }
}