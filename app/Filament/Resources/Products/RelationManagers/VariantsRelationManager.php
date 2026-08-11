<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Helpers\VariantAttributeHelper;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->required()
                        ->placeholder('e.g., Red - Large'),

                    TextInput::make('sku')
                        ->required()
                        ->unique(ignoreRecord: true),

                    FileUpload::make('image')
                        ->label('Variant Image (Optional)')
                        ->image()
                        ->disk('public')
                        ->visibility('public')
                        ->directory('products/variants')
                        ->columnSpanFull(),

                    // --- MULTIPLE GALLERY IMAGES FOR VARIANT ---
                    FileUpload::make('gallery_images')
                        ->label('Variant Gallery Images (Optional)')
                        ->multiple()
                        ->reorderable()
                        ->image()
                        ->disk('public')
                        ->visibility('public')
                        ->directory('products/variants/gallery')
                        ->columnSpanFull(),
                    // --------------------------------------------

                    TextInput::make('mrp')
                        ->label('Variant MRP')
                        ->numeric()
                        ->prefix('₹'),

                    TextInput::make('selling_price')
                        ->label('Variant Selling Price')
                        ->numeric()
                        ->default(0.00)
                        ->required()
                        ->prefix('₹'),

                    TextInput::make('stock_quantity')
                        ->numeric()
                        ->default(0),

                    Toggle::make('is_active')
                        ->default(true),
                ]),

                Repeater::make('attributes')
                    ->label('Variant Attributes (Dynamic)')
                    ->helperText('Attribute names are normalized automatically — "size", "Size" and "SIZE" all become the same "Size" attribute. Pick "Color" as the type to get a color picker.')
                    ->columns(3)
                    ->defaultItems(0)
                    ->createItemButtonLabel('Add Attribute')
                    ->reorderable(false)
                    ->afterStateHydrated(function (Repeater $component, $state): void {
                        // Convert the stored map ({"Size": "XL", "Color": "#ff0000"})
                        // into repeater rows for editing.
                        $rows = [];

                        foreach (VariantAttributeHelper::normalizeMap($state) as $key => $value) {
                            $rows[] = VariantAttributeHelper::isColorKey($key)
                                ? ['name' => $key, 'value' => null, 'color' => $value]
                                : ['name' => $key, 'value' => $value, 'color' => null];
                        }

                        if ($rows !== []) {
                            $component->state($rows);
                        }
                    })
                    // The repeater's built-in dehydrated-state mutator would flatten the
                    // rows with array_values(), so it is replaced with one that converts
                    // the rows back into a normalized attributes map.
                    ->mutateDehydratedStateUsing(function (?array $state): array {
                        $attributes = [];

                        foreach ($state ?? [] as $row) {
                            if (! is_array($row)) {
                                continue;
                            }

                            $key = VariantAttributeHelper::normalizeKey($row['name'] ?? null);

                            if ($key === null) {
                                continue;
                            }

                            $value = VariantAttributeHelper::isColorKey($key)
                                ? ($row['color'] ?? null)
                                : ($row['value'] ?? null);

                            if (is_string($value)) {
                                $value = trim($value);
                            }

                            if ($value === null || $value === '') {
                                continue;
                            }

                            // Normalized keys merge case-insensitive duplicates (last one wins).
                            $attributes[$key] = $value;
                        }

                        return $attributes;
                    })
                    ->schema([
                        TextInput::make('name')
                            ->label('Type')
                            ->placeholder('e.g., Size')
                            ->datalist(VariantAttributeHelper::COMMON_KEYS)
                            ->required()
                            ->live(debounce: 300),

                        TextInput::make('value')
                            ->label('Value')
                            ->placeholder('e.g., XL')
                            ->required()
                            ->visible(fn (Get $get): bool => ! VariantAttributeHelper::isColorKey($get('name'))),

                        ColorPicker::make('color')
                            ->label('Color')
                            ->helperText('Pick a color from the panel, or type any custom value (hex, rgb or a name like "Navy Blue").')
                            ->required()
                            ->visible(fn (Get $get): bool => VariantAttributeHelper::isColorKey($get('name'))),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                ImageColumn::make('image')
                    ->label('Image')
                    ->disk('public')
                    ->circular()
                    ->placeholder('-'),

                TextColumn::make('name')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),

                TextColumn::make('attributes')
                    ->label('Attributes')
                    ->formatStateUsing(function ($state): string {
                        if (! is_array($state) || $state === []) {
                            return '—';
                        }

                        return collect($state)
                            ->map(fn ($value, $key) => "{$key}: {$value}")
                            ->implode(' • ');
                    })
                    ->wrap(),

                TextColumn::make('selling_price')
                    ->label('Price')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->filters([
                //
            ])
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
