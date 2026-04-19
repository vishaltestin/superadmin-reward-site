<?php

namespace App\Filament\Resources\LandingPageTemplates\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\FileUpload;

class LandingPageTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                
                // --- CORE IDENTITY ---
                Section::make('Page Settings')->schema([
                    Grid::make(3)->schema([
                        Select::make('event_id')
                            ->relationship('event', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Trigger Event'),

                        Select::make('company_id')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Company (Leave blank for Global Master)'),

                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->required()
                            ->placeholder('e.g., Master Cashback Page')
                            ->label('Internal Admin Name'),

                        TextInput::make('title')
                            ->required()
                            ->placeholder('e.g., Claim Your Rewards!')
                            ->label('Public Facing Title (Editable by Client)'),
                    ]),
                    
                    Toggle::make('is_active')
                        ->default(true)
                        ->label('Active Status'),
                ]),

                // --- GLOBAL TOKENS & SEO (JSON COLUMNS) ---
                Grid::make(2)->schema([
                    Section::make('Global Theme Tokens')
                        ->description('High-level branding applied to the whole page.')
                        ->columnSpan(1)
                        ->schema([
                            KeyValue::make('global_theme_tokens')
                                ->keyLabel('CSS Token / Variable')
                                ->valueLabel('Value')
                                ->addActionLabel('Add Token')
                                ->default([
                                    'primaryColor' => '#4f46e5',
                                    'fontFamily' => 'Inter, sans-serif',
                                ]),
                        ]),

                    Section::make('SEO & Social Sharing')
                        ->description('How this page looks when linked in Slack/WhatsApp.')
                        ->columnSpan(1)
                        ->schema([
                            TextInput::make('seo_meta.title')->label('SEO Title'),
                            Textarea::make('seo_meta.description')->label('SEO Description')->rows(2),
                            FileUpload::make('seo_meta.og_image')->label('Social Share Image')->image(),
                        ]),
                ]),

                // --- THE REACT COMPONENT BUILDER ---
                Section::make('Page Structure (React Schema)')
                    ->description('Build the component array that the React frontend will render.')
                    ->schema([
                    
                    Builder::make('page_schema')
                        ->label('Landing Page Sections')
                        ->blocks([
                            
                            Builder\Block::make('section')
                                ->label('UI Section')
                                ->icon('heroicon-o-view-columns')
                                ->schema([
                                    Grid::make(3)->schema([
                                        TextInput::make('id')->required()->default(fn() => uniqid('sec_')),
                                        TextInput::make('type')->required()->placeholder('e.g., hero, features'),
                                        TextInput::make('name')->required()->placeholder('e.g., Shopping Hero'),
                                    ]),
                                    
                                    Toggle::make('isVisible')->default(true),
                                    
                                    Repeater::make('properties')
                                        ->label('Configurable Properties')
                                        ->schema([
                                            Grid::make(2)->schema([
                                                TextInput::make('key')->required()->placeholder('e.g., titleColor'),
                                                TextInput::make('label')->required()->placeholder('e.g., Title Color'),
                                                Select::make('type')
                                                    ->options([
                                                        'text' => 'Text Input',
                                                        'textarea' => 'Text Area',
                                                        'color' => 'Color Picker',
                                                        'select' => 'Dropdown',
                                                        'image' => 'Image Upload',
                                                        'array' => 'Data Array',
                                                    ])->required(),
                                                TextInput::make('value')->required(),
                                            ]),
                                            
                                            Repeater::make('options')
                                                ->simple(TextInput::make('option_value')->required())
                                                ->label('Dropdown Options (if type is select)')
                                                ->collapsed(),
                                        ])
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                        ->cloneable(),
                                ])
                        ])
                        ->collapsible()
                        ->cloneable()
                ]),
            ]);
    }
}