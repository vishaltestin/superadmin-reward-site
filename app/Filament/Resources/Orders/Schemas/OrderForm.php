<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(3)->schema([
                Grid::make(1)->schema([
                    Section::make('Order Management')->schema([
                        TextInput::make('order_number')
                            ->extraAttributes(['class' => 'font-bold'])
                            ->readOnly(),

                        Select::make('status')
                            ->options([
                                'pending' => 'Pending (Awaiting Payment)',
                                'paid' => 'Paid (Ready to Process)',
                                'processing' => 'Processing (Packing/Generating)',
                                'shipped' => 'Shipped / Dispatched',
                                'completed' => 'Completed / Delivered',
                                'cancelled' => 'Cancelled',
                                'failed' => 'Payment Failed',
                            ])
                            ->required()
                            ->native(false)
                            ->columnSpanFull(),
                    ]),

                    Section::make('Customer & Tenancy')->schema([
                        Select::make('company_id')
                            ->relationship('company', 'name')
                            ->disabled(),

                        Select::make('user_id')
                            ->relationship('user', 'email')
                            ->disabled(),
                    ]),
                ])->columnSpan(2),

                Grid::make(1)->schema([
                    Section::make('Financial Ledger')->schema([
                        TextInput::make('total_amount')
                            ->prefix('₹')
                            ->numeric()
                            ->readOnly(),

                        TextInput::make('gst_total')
                            ->prefix('₹')
                            ->numeric()
                            ->readOnly(),

                        TextInput::make('points_used')
                            ->numeric()
                            ->readOnly(),

                        TextInput::make('fiat_paid')
                            ->prefix('₹')
                            ->numeric()
                            ->readOnly(),

                        TextInput::make('payment_gateway_reference')
                            ->placeholder('N/A')
                            ->readOnly(),
                    ]),
                ])->columnSpan(1),

            ]),
        ]);
    }
}