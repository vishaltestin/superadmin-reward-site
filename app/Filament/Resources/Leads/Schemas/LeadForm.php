<?php
namespace App\Filament\Resources\Leads\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(2)->schema([

                    Section::make('Applicant Details')->schema([
                        TextInput::make('first_name')->required(),
                        TextInput::make('last_name')->required(),
                        TextInput::make('email')->email()->required(),
                        TextInput::make('mobile')->tel()->required(),
                        TextInput::make('designation'),
                        TextInput::make('department'),
                    ])->columnSpan(1),

                    Section::make('Company Request')->schema([
                        TextInput::make('company_name')->required(),
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
                            ])->required(),
                        Select::make('status')
                            ->options([
                                'pending'  => 'Pending',
                                'approved' => 'Approved (Converted)',
                                'rejected' => 'Rejected',
                            ])
                            ->default('pending')
                            ->required(),
                    ])->columnSpan(1),

                ]),
            ]);
    }
}
