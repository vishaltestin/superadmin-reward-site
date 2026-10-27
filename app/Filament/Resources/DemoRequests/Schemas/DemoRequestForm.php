<?php
namespace App\Filament\Resources\DemoRequests\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DemoRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Prospect Information')
                    ->schema([
                        TextInput::make('first_name')->readOnly(),
                        TextInput::make('last_name')->readOnly(),
                        TextInput::make('email')->readOnly(),
                        TextInput::make('mobile')->readOnly(),
                        TextInput::make('company_name')->readOnly(),
                        TextInput::make('department')->readOnly(),
                        TextInput::make('designation')->readOnly(),
                        TextInput::make('number_of_employee')
                            ->label('Number of Employees')
                            ->readOnly(),
                        TextInput::make('how_did_you_hear_about_us')
                            ->label('How did you hear about us?')
                            ->readOnly()
                            ->columnSpanFull(),
                        Select::make('status')
                            ->options([
                                'new'            => 'New Request',
                                'contacted'      => 'Contacted',
                                'demo_scheduled' => 'Demo Scheduled',
                                'closed'         => 'Closed / Won',
                            ])
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Message')
                    ->schema([
                        Textarea::make('message')
                            ->readOnly()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
