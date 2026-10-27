<?php
namespace App\Filament\Resources\Companies\Schemas;

use App\Models\Company;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Company Setup')
                ->tabs([
                    Tabs\Tab::make('Lead & Basic Info')
                        ->icon('heroicon-o-building-office')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('name')
                                        ->required()
                                        ->label('Company Name')
                                        ->live(debounce: 500)
                                        ->afterStateUpdated(function (
                                            Set $set,
                                            ?string $state,
                                            string $operation,
                                            $get
                                        ) {
                                            if ($operation === 'create' && empty($get('alias'))) {
                                                $set('alias', Str::slug($state));
                                            }
                                        }),

                                    Select::make('number_of_employee')
                                        ->label('Company Size')
                                        ->options([
                                            '0-50'       => '0 - 50',
                                            '51-200'     => '51 - 200',
                                            '201-500'    => '201 - 500',
                                            '501-1000'   => '501 - 1000',
                                            '1001-5000'  => '1001 - 5000',
                                            '5001-10000' => '5001 - 10000',
                                            '10000+'     => '10000+',
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
                                        ->relationship(
                                            name: 'categories',
                                            titleAttribute: 'name',
                                            modifyQueryUsing: fn($query) => $query->with('parent'),
                                        )
                                        ->getOptionLabelFromRecordUsing(fn($record) => $record->tree_name)
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
                                    TextInput::make('gst_no')
                                        ->label('GST Number'),

                                    TextInput::make('pan_no')
                                        ->label('PAN Number'),

                                    Placeholder::make('gst_document_link')
                                        ->label('GST Document')
                                        ->content(function (?Company $record) {
                                            if (! $record?->gst_document_path) {
                                                return 'No GST document uploaded.';
                                            }

                                            $url = Storage::disk('local')->temporaryUrl(
                                                $record->gst_document_path,
                                                now()->addMinutes(30),
                                            );

                                            return new HtmlString(
                                                '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">Open GST document</a>'
                                                . '<span style="margin-left:8px;color:#6b7280">Link expires in 30 minutes.</span>'
                                            );
                                        }),

                                    Placeholder::make('pan_document_link')
                                        ->label('PAN Document')
                                        ->content(function (?Company $record) {
                                            if (! $record?->pan_document_path) {
                                                return 'No PAN document uploaded.';
                                            }

                                            $url = Storage::disk('local')->temporaryUrl(
                                                $record->pan_document_path,
                                                now()->addMinutes(30),
                                            );

                                            return new HtmlString(
                                                '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">Open PAN document</a>'
                                                . '<span style="margin-left:8px;color:#6b7280">Link expires in 30 minutes.</span>'
                                            );
                                        }),

                                    Select::make('industry')
                                        ->options([
                                            'IT & Software' => 'IT & Software',
                                            'Finance'       => 'Finance',
                                            'Healthcare'    => 'Healthcare',
                                            'Manufacturing' => 'Manufacturing',
                                            'Retail'        => 'Retail',
                                            'Others'        => 'Others',
                                        ]),

                                    Select::make('verification_status')
                                        ->label('KYC / Verification Status')
                                        ->options([
                                            'pending'   => 'Pending (No Docs)',
                                            'submitted' => 'Submitted (Awaiting Review)',
                                            'verified'  => 'Verified (Approved)',
                                            'rejected'  => 'Rejected (Needs Fix)',
                                        ])
                                        ->default('pending')
                                        ->helperText('Update this after reviewing the GST and PAN details.'),

                                    Textarea::make('address')
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    Tabs\Tab::make('Storefront & Status')
                        ->icon('heroicon-o-globe-alt')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('alias')
                                        ->unique(
                                            ignoreRecord: true,
                                            modifyRuleUsing: fn($rule) => $rule->whereNull('deleted_at'),
                                        )
                                        ->label('Storefront URL Alias')
                                        ->prefix('rewardsapp.in/ecom/'),

                                    TextInput::make('points_name')
                                        ->default('Points')
                                        ->required(),

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
                                            Toggle::make('is_approved')
                                                ->label('Lead Approved')
                                                ->helperText('Flip this to approve the incoming lead.'),

                                            Toggle::make('is_active')
                                                ->label('Account Active')
                                                ->default(true),
                                        ])
                                        ->columns(3)
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }
}
