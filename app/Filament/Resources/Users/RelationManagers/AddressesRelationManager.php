<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $recordTitleAttribute = 'type';
    
    protected static ?string $title = 'Saved Addresses Book';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Grid::make(2)->schema([
                    Select::make('type')
                        ->options([
                            'home' => 'Home',
                            'office' => 'Office',
                            'other' => 'Other',
                        ])
                        ->required()
                        ->default('home'),
                        
                    Toggle::make('is_default')
                        ->label('Default Address')
                        ->inline(false)
                        ->default(false),
                ]),

                Grid::make(2)->schema([
                    TextInput::make('contact_name')
                        ->required()
                        ->maxLength(255),
                        
                    TextInput::make('contact_mobile')
                        ->required()
                        ->tel()
                        ->maxLength(255),
                ]),

                TextInput::make('address_line_1')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                    
                TextInput::make('address_line_2')
                    ->maxLength(255)
                    ->columnSpanFull(),

                Grid::make(3)->schema([
                    TextInput::make('city')
                        ->required()
                        ->maxLength(255),
                        
                    TextInput::make('state')
                        ->required()
                        ->maxLength(255),
                        
                    TextInput::make('pincode')
                        ->required()
                        ->numeric()
                        ->maxLength(10),
                ]),
                
                TextInput::make('country')
                    ->default('India')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'home' => 'success',
                        'office' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('contact_name')
                    ->label('Contact')
                    ->description(fn ($record) => $record->contact_mobile)
                    ->searchable(),

                TextColumn::make('city')
                    ->label('City/State')
                    ->description(fn ($record) => $record->state)
                    ->searchable(),

                TextColumn::make('pincode')
                    ->label('Pincode')
                    ->searchable(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
            ])
            ->filters([])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}