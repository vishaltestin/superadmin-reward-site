<?php

namespace App\Filament\Resources\LandingPageTemplates\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\FileUpload;

class LandingPageTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([

                // --- CORE SETTINGS ---
                Section::make('Page Settings')->schema([
                    Grid::make(3)->schema([
                        Select::make('event_id')
                            ->relationship('event', 'title')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('company_id')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload(),

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
                        TextInput::make('name')->required(),
                        TextInput::make('title')->required(),
                    ]),

                    Toggle::make('is_active')->default(true),
                ]),

                Section::make('Global Theme Tokens')->schema([
                    KeyValue::make('global_theme_tokens')
                        ->default([
                            'primaryColor' => '#4f46e5',
                            'fontFamily' => 'Inter, sans-serif',
                        ]),
                ]),

                // --- SEO ---
                Section::make('SEO')->schema([
                    TextInput::make('seo_meta.title'),
                    Textarea::make('seo_meta.description'),
                    FileUpload::make('seo_meta.og_image')
                        ->image()
                        ->disk('public')
                        ->directory('seo'),
                ]),

                // --- RAW JSON EDITOR ---
                Section::make('Page Schema (JSON)')
                    ->description('Paste or edit your full React schema here.')
                    ->schema([

                        Textarea::make('page_schema')
                            ->rows(30)
                            ->required()
                            ->formatStateUsing(fn ($state) =>
                                json_encode($state ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                            )
                            ->dehydrateStateUsing(function ($state) {
                                $decoded = json_decode($state, true);

                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    throw new \Exception('Invalid JSON format');
                                }

                                return $decoded;
                            })
                            ->extraAttributes([
                                'style' => 'font-family: monospace; min-height: 500px;',
                            ]),
                    ]),
            ]);
    }
}