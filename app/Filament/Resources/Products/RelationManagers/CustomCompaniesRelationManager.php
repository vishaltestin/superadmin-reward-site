<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions;

class CustomCompaniesRelationManager extends RelationManager
{
    protected static string $relationship = 'customCompanies';
protected static ?string $inverseRelationship = 'customProducts';
    protected static ?string $recordTitleAttribute = 'name';
    
    protected static ?string $title = 'Company Exceptions & Branding';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Exception Rules')->schema([
                    Toggle::make('is_excluded')
                        ->label('Exclude this product completely?')
                        ->helperText('If ON, this company will never see this product.')
                        ->columnSpanFull(),

                    TextInput::make('override_name')
                        ->label('Custom Product Name')
                        ->placeholder('e.g., Ford Branded Mug'),

                    FileUpload::make('override_image')
                        ->label('Custom Main Image')
                        ->image()
                        ->directory('products/overrides'),

                    TextInput::make('override_mrp')
                        ->label('Custom MRP')
                        ->numeric()
                        ->prefix('₹'),

                    TextInput::make('override_selling_price')
                        ->label('Custom Selling Price')
                        ->numeric()
                        ->prefix('₹'),
                ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Company Name')
                    ->weight('bold')
                    ->searchable(),

                IconColumn::make('is_excluded')
                    ->label('Excluded')
                    ->boolean(),

                TextColumn::make('override_name')
                    ->label('Override Name')
                    ->default('-'),

                TextColumn::make('override_selling_price')
                    ->label('Override Price')
                    ->money('INR')
                    ->default('-'),

                ImageColumn::make('override_image')
                    ->label('Override Image')
                    ->circular(),
            ])
            ->filters([])
            ->headerActions([
                Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn (Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Toggle::make('is_excluded')->label('Exclude Product completely?')->columnSpanFull(),
                        TextInput::make('override_name')->label('Override Name'),
                        FileUpload::make('override_image')->image()->directory('products/overrides'),
                        TextInput::make('override_mrp')->numeric()->prefix('₹'),
                        TextInput::make('override_selling_price')->numeric()->prefix('₹'),
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}