<?php
namespace App\Filament\Resources\Promotions\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Get;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Promotion Details')->schema([
                    TextInput::make('internal_name')
                        ->required()
                        ->placeholder('e.g., Q3 Tech Industry Promo'),
                    Toggle::make('is_active')
                        ->default(true),
                ])->columns(2),

                Section::make('Targeting')->schema([
                    Select::make('target_type')
                        ->options([
                            'global'             => 'Global (All Companies)',
                            'industry'           => 'Specific Industries',
                            'specific_companies' => 'Specific Companies',
                        ])
                        ->default('global')
                        ->live()
                        ->required(),

                    TagsInput::make('target_data')
    ->label('Specify Industries')
    ->placeholder('Press enter to add industry (e.g., IT & Software)')
    ->visible(fn (Get $get) => $get('target_type') === 'industry')
    ->dehydrated(fn (Get $get) => $get('target_type') === 'industry')  
    ->required(fn (Get $get) => $get('target_type') === 'industry'),

Select::make('target_data')
    ->label('Select Companies')
    ->multiple()
    ->options(\App\Models\Company::pluck('name', 'id'))
    ->visible(fn (Get $get) => $get('target_type') === 'specific_companies')
    ->dehydrated(fn (Get $get) => $get('target_type') === 'specific_companies')  
    ->required(fn (Get $get) => $get('target_type') === 'specific_companies'),
                ]),

                Section::make('Format & Content')->schema([
                    Select::make('format')
                        ->options([
                            'hero_banner'      => 'Hero Banner',
                            'featured_product' => 'Featured Product Card',
                        ])
                        ->default('hero_banner')
                        ->live()
                        ->required(),

                    Group::make([
                        FileUpload::make('format_data.image')
                            ->label('Banner Image')
                            ->image()
                            ->disk('public')
                            ->directory('promotions/banners')
                            ->required(),
                        TextInput::make('format_data.target_url')
                            ->label('Click URL')
                            ->url()
                            ->nullable(),
                    ])->visible(fn (Get $get) => $get('format') === 'hero_banner'),

                    Group::make([
                        Select::make('format_data.product_id')
                            ->label('Select Product')
                            ->options(\App\Models\Product::pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        TextInput::make('format_data.badge_text')
                            ->label('Custom Badge (e.g., "Hot Deal")')
                            ->nullable(),
                    ])->visible(fn (Get $get) => $get('format') === 'featured_product'),
                ]),

                Section::make('Scheduling')->schema([
                    DateTimePicker::make('starts_at'),
                    DateTimePicker::make('ends_at'),
                ])->columns(2),
            ]);
    }
}