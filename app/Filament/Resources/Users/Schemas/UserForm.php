<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        Select::make('user_type')
                            ->helperText('Select "Sub Admin" to assign vertical permissions')
                            ->options([
                                'super_admin' => 'Super Admin',
                                'business_head' => 'Business Head',
                                'sub_admin' => 'Sub Admin',
                                'rewardee' => 'Rewardee',
                            ])
                            ->required()
                            ->live(),
                        
                        Select::make('company_id')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get) => $get('user_type') !== 'super_admin')
                            ->required(fn (Get $get) => $get('user_type') !== 'super_admin'),

                        TextInput::make('first_name')
                            ->required(),

                        TextInput::make('last_name')
                            ->required(),

                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->required(),

                        TextInput::make('mobile')
                            ->tel(),

                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create'),

                        Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),

                Section::make('Sub-Admin Setup')
                    ->description('Assign which verticals this sub-admin is allowed to manage.')
                    ->schema([
                        Select::make('managedVerticals')
                            ->relationship('managedVerticals', 'name')
                            ->multiple()
                            ->preload()
                            ->label('Permitted Verticals'),
                    ])
                    ->visible(fn (Get $get) => $get('user_type') === 'sub_admin'),
            ]);
    }
}