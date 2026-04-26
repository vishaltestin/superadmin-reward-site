<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ColorPicker;
use Illuminate\Database\Eloquent\Builder;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Product Builder')
                ->tabs([
                    // TAB 1: Basic Identity & Pricing
                   Tab::make('Core Details')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('type')
                                    ->options([
                                        'physical' => 'Physical Item',
                                        'digital' => 'Digital Voucher',
                                        'experience' => 'Experience',
                                    ])
                                    ->required()
                                    ->default('physical')
                                    ->live(),

                                TextInput::make('name')
                                    ->required()
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Set $set, ?string $state, string $operation, $get) {
                                        if ($operation === 'create' && empty($get('slug'))) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),

                                TextInput::make('slug')
                                    ->required()
                                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->whereNull('deleted_at')),
                                    // ->readOnly(fn (string $operation): bool => $operation === 'edit'),

                                TextInput::make('sku')
                                    ->label('Global SKU')
                                    ->unique(ignoreRecord: true),

                                Select::make('brand_id')
        ->relationship('brand', 'name')
        ->searchable()
        ->preload()
        ->label('Brand')
        ->createOptionForm([ // <-- This allows Admins to create a Brand instantly without leaving the page!
            TextInput::make('name')->required(),
            FileUpload::make('logo')->image()->disk('public')->directory('brands'),
            Toggle::make('is_active')->default(true),
        ]),
        TextInput::make('warranty_info')
        ->label('Warranty Information')
        ->placeholder('e.g., 1 Year Manufacturer Warranty')
        ->columnSpanFull(),

                                Section::make('Fiat Pricing Strategy')
                                ->columnSpanFull()
                                    ->schema([
                                        TextInput::make('mrp')
                                            ->label('MRP (Crossed-out)')
                                            ->numeric()
                                            ->prefix('₹'),
                                            
                                        TextInput::make('selling_price')
                                            ->label('Actual Selling Price')
                                            ->numeric()
                                            ->required()
                                            ->default(0.00)
                                            ->prefix('₹'),
                                        TextInput::make('gst_percentage')
                ->label('GST (%)')
                ->numeric()
                ->default(0.00)
                ->suffix('%')
                ->helperText('Used for invoicing and taxation.'),
                                    ])->columns(3),
                            ]),
                        ]),

                   Tab::make('Taxonomy & Tags')
                        ->icon('heroicon-o-rectangle-stack')
                        ->schema([
                            Select::make('category_id')
    ->relationship(
        name: 'primaryCategory', 
        titleAttribute: 'name',
        modifyQueryUsing: fn (Builder $query) => $query->with('parent') 
    )
    ->getOptionLabelFromRecordUsing(fn ($record) => $record->tree_name)
    ->searchable()
    ->preload()
    ->required()
    ->label('Primary Category'),

Select::make('secondaryCategories')
    ->relationship(
        name: 'secondaryCategories', 
        titleAttribute: 'name',
        modifyQueryUsing: fn (Builder $query) => $query->with('parent')
    )
    ->getOptionLabelFromRecordUsing(fn ($record) => $record->tree_name)
    ->multiple()
    ->searchable()
    ->preload()
    ->label('Secondary Categories'),
                                
                            TagsInput::make('tags')
                                ->label('Product Tags')
                                ->placeholder('e.g., bestseller, new-arrival')
                                ->helperText('Loose keywords for internal search and storefront filtering.'),
                        ]),

                    // TAB 3: Content & Specs
                   Tab::make('Content & Specs')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            TextInput::make('short_description')
                                ->maxLength(255)
                                ->columnSpanFull(),

                            RichEditor::make('long_description')
                                ->columnSpanFull(),

                            TagsInput::make('key_features')
                                ->label('Key Features (Bullet Points)')
                                ->columnSpanFull(),

                            KeyValue::make('specifications')
                                ->keyLabel('Spec Name (e.g. Material)')
                                ->valueLabel('Value (e.g. Cotton)')
                                ->columnSpanFull(),

                            RichEditor::make('terms_and_conditions')
                                ->visible(fn (Get $get) => $get('type') === 'digital')
                                ->columnSpanFull(),
                        ]),

                    // TAB 4: Media
                   Tab::make('Media & Visibility')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            FileUpload::make('main_image')
                                ->image()
                                ->disk('public')
                                ->visibility('public')
                                ->directory('products/main')
                                ->columnSpanFull(),

                            FileUpload::make('gallery_images')
                                ->image()
                                ->disk('public')
                                ->visibility('public')
                                ->multiple()
                                ->directory('products/gallery')
                                ->columnSpanFull(),
                            
                            TextInput::make('video_url')
        ->label('Promo Video URL')
        ->placeholder('https://youtube.com/watch?...')
        ->url()
        ->columnSpanFull(),
        TextInput::make('sort_order')
        ->label('Storefront Sort Priority')
        ->numeric()
        ->default(0)
        ->helperText('Higher numbers appear first. 0 is default.'),

                            Toggle::make('is_active')
                                ->default(true)
                                ->columnSpanFull(),
                        ]),

                    // TAB 5: Specific Type Data
                    Tab::make('Specific Details')
                    ->visible(fn (Get $get) => $get('type') !== 'physical')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema([
                            
                            // --- VOUCHER FIELDS ---
                            Section::make('Voucher / Offer Details')
                                ->visible(fn (Get $get) => $get('type') === 'digital')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('type_data.couponCode')->label('Coupon Code'),
                                        DatePicker::make('type_data.validUntil')->label('Valid Until'),
                                        TextInput::make('type_data.redemptionLink')->label('Redemption Link')->url(),
                                        TextInput::make('type_data.storeName')->label('Store Name'),
                                        ColorPicker::make('type_data.backgroundColor')->label('Background Color'),
                                        TextInput::make('type_data.phone')->label('Store Phone')->tel(),
                                        TextInput::make('type_data.website')->label('Store Website')->url(),
                                        TextInput::make('type_data.pincode')->label('Pincode'),
                                        TextInput::make('type_data.mapLocation_lat')->label('Latitude')->numeric(),
                                        TextInput::make('type_data.mapLocation_lng')->label('Longitude')->numeric(),
                                    ]),
                                    RichEditor::make('type_data.aboutBrand')->label('About Brand')->columnSpanFull(),
                                    TextInput::make('type_data.address')->label('Full Address')->columnSpanFull(),
                                ]),

                            // --- TRAVEL EXPERIENCE FIELDS ---
                            Section::make('Travel / Experience Details')
                                ->visible(fn (Get $get) => $get('type') === 'experience')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('type_data.destination')->label('Destination (e.g., Wayanad, Kerala)'),
                                        TextInput::make('type_data.duration')->label('Duration (e.g., 3 Days)'),
                                        TextInput::make('type_data.groupSize')->label('Group Size (e.g., 2-8 People)'),
                                        TextInput::make('type_data.guest')->label('Number of Guests')->numeric(),
                                        DatePicker::make('type_data.departureDate')->label('Departure Date'),
                                        TimePicker::make('type_data.departureTime')->label('Departure Time'),
                                        TextInput::make('type_data.availableDates')->label('Available Dates (Comma separated)'),
                                        TextInput::make('type_data.Availability')->label('Promo Video / Availability Link')->url(),
                                    ]),
                                    RichEditor::make('type_data.includes_excludes')->label('Includes & Excludes')->columnSpanFull(),
                                ]),
                        ]),
                ])->columnSpanFull(),
                
        ]);
    }
}