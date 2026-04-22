<?php

namespace App\Filament\Resources\DemoRequests\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;

class DemoRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Prospect Information')->schema([
                    TextInput::make('first_name')->readOnly(),
                    TextInput::make('last_name')->readOnly(),
                    TextInput::make('email')->readOnly(),
                    TextInput::make('mobile')->readOnly(),
                    TextInput::make('company_name')->readOnly(),
                    
                    Select::make('status')
                        ->options([
                            'new' => 'New Request',
                            'contacted' => 'Contacted',
                            'demo_scheduled' => 'Demo Scheduled',
                            'closed' => 'Closed / Won',
                        ])
                        ->required()
                        ->columnSpanFull(),
                ])->columns(2),

                Section::make('Message')->schema([
                    Textarea::make('message')
                        ->readOnly()
                        ->columnSpanFull(),
                ]),
            ]);
    }
}