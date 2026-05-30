<?php
namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CustomCompaniesRelationManager extends RelationManager
{
    protected static string $relationship          = 'customCompanies';
    protected static ?string $inverseRelationship  = 'customProducts';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $title                = 'Company Exceptions & Branding';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Base Product Exceptions & Rules')->schema([
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
                        ->disk('public')
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

                Section::make('Variant-Specific Branching Rules')
                    ->description('Specify distinct branding and pricing rules for individual item configurations.')
                    ->visible(fn() => $this->getOwnerRecord()->variants()->exists())
                    ->schema([
                        Repeater::make('variant_overrides')
                            ->label('Configured Variants')
                            ->columns(3)
                            ->createItemButtonLabel('Add Variant Override')
                            ->defaultItems(0)
                            ->afterStateHydrated(function (Repeater $component, ?Model $record) {
                                if (! $record) {
                                    return;
                                }

                                $productVariantIds = $this->getOwnerRecord()->variants()->pluck('id')->toArray();
                                $currentOverrides  = DB::table('company_product_variant')
                                    ->where('company_id', $record->id)
                                    ->whereIn('product_variant_id', $productVariantIds)
                                    ->get()
                                    ->map(fn($item) => [
                                        'product_variant_id'     => $item->product_variant_id,
                                        'override_selling_price' => $item->override_selling_price,
                                        'override_image'         => $item->override_image ? [$item->override_image] : [],
                                    ])->toArray();
                                $component->state($currentOverrides);
                            })
                            ->saveRelationshipsUsing(function (?Model $record, array $state) {
                                if (! $record) {
                                    return;
                                }

                                $productVariantIds = $this->getOwnerRecord()->variants()->pluck('id')->toArray();
                                DB::table('company_product_variant')
                                    ->where('company_id', $record->id)
                                    ->whereIn('product_variant_id', $productVariantIds)
                                    ->delete();
                                foreach ($state as $row) {
                                    if (empty($row['product_variant_id'])) {
                                        continue;
                                    }

                                    $overrideImage = null;
                                    if (! empty($row['override_image'])) {
                                        $overrideImage = is_array($row['override_image'])
                                            ? (current($row['override_image']) ?: array_key_first($row['override_image']))
                                            : $row['override_image'];
                                    }
                                    DB::table('company_product_variant')->insert([
                                        'company_id'             => $record->id,
                                        'product_variant_id'     => $row['product_variant_id'],
                                        'override_selling_price' => $row['override_selling_price'] ?: null,
                                        'override_image'         => $overrideImage,
                                        'created_at'             => now(),
                                        'updated_at'             => now(),
                                    ]);
                                }
                            })
                            ->schema([
                                Select::make('product_variant_id')
                                    ->label('Select Variant')
                                    ->options(fn() => $this->getOwnerRecord()->variants->pluck('name', 'id'))
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                TextInput::make('override_selling_price')->label('Custom Price')->numeric()->prefix('₹'),
                                FileUpload::make('override_image')->label('Custom Image')->image()->disk('public')->directory('products/variants/overrides'),
                            ]),
                    ]),

                Section::make('Custom Bulk Tier Pricing')
                    ->description('Configure contract-negotiated scale pricing tiers specifically for this client.')
                    ->schema([
                        Repeater::make('custom_tier_prices')
                            ->label('Negotiated Rates Sheets')
                            ->columns(3)
                            ->createItemButtonLabel('Add Custom Rate Tier')
                            ->defaultItems(0)

                            ->afterStateHydrated(function (Repeater $component, ?Model $record) {
                                if (! $record) {
                                    return;
                                }

                                $currentTiers = \App\Models\CompanyProductTierPrice::where('company_id', $record->id)
                                    ->where('product_id', $this->getOwnerRecord()->id)
                                    ->get()
                                    ->map(fn($item) => [
                                        'product_variant_id' => $item->product_variant_id,
                                        'min_quantity'       => $item->min_quantity,
                                        'selling_price'      => $item->selling_price,
                                    ])
                                    ->toArray();

                                $component->state($currentTiers);
                            })

                            ->saveRelationshipsUsing(function (?Model $record, array $state) {
                                if (! $record) {
                                    return;
                                }

                                \App\Models\CompanyProductTierPrice::where('company_id', $record->id)
                                    ->where('product_id', $this->getOwnerRecord()->id)
                                    ->delete();

                                foreach ($state as $row) {
                                    if (empty($row['min_quantity']) || empty($row['selling_price'])) {
                                        continue;
                                    }

                                    \App\Models\CompanyProductTierPrice::create([
                                        'company_id'         => $record->id,
                                        'product_id'         => $this->getOwnerRecord()->id,
                                        'product_variant_id' => $row['product_variant_id'] ?: null,
                                        'min_quantity'       => $row['min_quantity'],
                                        'selling_price'      => $row['selling_price'],
                                    ]);
                                }
                            })
                            ->schema([
                                Select::make('product_variant_id')
                                    ->label('Applies To')
                                    ->options(fn() => $this->getOwnerRecord()->variants->pluck('name', 'id'))
                                    ->placeholder('All Variants (Product Global Default)')
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('min_quantity')
                                    ->label('Min Quantity Required')
                                    ->numeric()
                                    ->required()
                                    ->minValue(2),

                                TextInput::make('selling_price')
                                    ->label('Negotiated Price Per Unit')
                                    ->numeric()
                                    ->required()
                                    ->prefix('₹'),
                            ]),
                    ]),
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
                    ->disk('public')
                    ->circular(),
            ])
            ->filters([])
            ->headerActions([
                Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn(Actions\AttachAction $action): array=> [
                        $action->getRecordSelect(),
                        Toggle::make('is_excluded')->label('Exclude Product completely?')->columnSpanFull(),
                        TextInput::make('override_name')->label('Override Name'),
                        FileUpload::make('override_image')->image()->disk('public')->directory('products/overrides'),
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
