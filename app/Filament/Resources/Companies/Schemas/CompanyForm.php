<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CompanyForm
{
    public static function configure(Schema $schema) : Schema
    {
        return $schema->components([
            Tabs
                ::make('Company Setup')
                ->tabs([
                    Tabs\Tab
                    ::make('Lead & Basic Info')
                    ->icon('heroicon-o-building-office')
                    ->schema([
                        Grid
                        ::make(2)
                        ->schema([
                            TextInput
                            ::make('name')
                            ->required()
                            ->label('Company Name')
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Set $set, ?string $state, string $operation, $get) {
    if ($operation === 'create' && empty($get('alias'))) {
        $set('alias', Str::slug($state));
    }
}),

                        Select::make('number_of_employee')
                            ->label('Company Size')
                            ->options([
                                '0-50' => '0 - 50',
                                '51-200' => '51 - 200',
                                '201-500' => '201 - 500',
                                '501-1000' => '501 - 1000',
                                '1001-5000' => '1001 - 5000',
                                '5001-10000' => '5001 - 10000',
                                '10000+' => '10000+',
                            ]),

                        Select::make('verticals')
                            ->relationship('verticals', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->label('Assigned Verticals (Audiences)')
                            ->helperText(
                                'Select the user types/campaigns this company can run (e.g., Internal Employees).',
                            )
                            ->columnSpanFull(),

                        Select::make('categories')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->label('Allowed Product Categories')
                            ->helperText('Select which product categories this company can access.')
                            ->columnSpanFull(),
                        ]),
                    ]),

                Tabs\Tab::make('Compliance & Verification')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('gst_no')->label('GST Number'),
                                TextInput::make('pan_no')->label('PAN Number'),
                                Select::make('industry')
                                    ->options([
                                        'IT & Software' => 'IT & Software',
                                        'Finance' => 'Finance',
                                        'Healthcare' => 'Healthcare',
                                        'Manufacturing' => 'Manufacturing',
                                        'Retail' => 'Retail',
                                        'Others' => 'Others',
                                    ]),
                                Textarea::make('address')->columnSpanFull(),
                            ]),
                    ]),

                Tabs\Tab::make('Storefront & Status')
                    ->icon('heroicon-o-globe-alt')
                    ->schema([
                        Grid
                        ::make(2)
                        ->schema([
                            TextInput
                            ::make('alias')
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule) => $rule->whereNull('deleted_at'),
                            )
                            ->label('Storefront URL Alias')
                            ->prefix('rewardsapp.in/ecom/'),

                        TextInput::make('points_name')->default('Points')->required(),
                        TextInput::make('point_multiplier')
    ->label('Point Conversion Multiplier')
    ->helperText('Default is 1.00 (1:1 ratio). Set to 1.20 for a 20% point bonus on all funds added.')
    ->numeric()
    ->minValue(0.01)
    ->step(0.01)
    ->default(1.00)
    ->required(),

                        FileUpload::make('logo')
                            ->image()
                            ->disk('public')
                            ->directory('company-logos')
                            ->columnSpanFull(),

                        Section::make('Account Status')
                            ->schema([
                                // TextInput
                                // ::make('available_funds')
                                // ->numeric()
                                // ->default(0.00)
                                // ->prefix('₹'),

                            Toggle::make('is_approved')
                                ->label('Lead Approved')
                                ->helperText('Flip this to approve the incoming lead.'),

                            Toggle::make('is_active')->label('Account Active')->default(true),
                            ])->columns(3)->columnSpanFull(),
                        ]),
                    ]),
                ])->columnSpanFull(),
        ]);
    }
}