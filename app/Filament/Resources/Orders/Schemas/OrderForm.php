<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\KeyValue;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(3)->schema([
                
                // ----------------------------------------------------------------
                // LEFT COLUMN (Takes up 2/3 of the screen for management)
                // ----------------------------------------------------------------
                Grid::make(1)->schema([
                    
                    Section::make('Order Management')->schema([
                        TextInput::make('order_number')
                            ->extraAttributes(['class' => 'font-bold'])
                            ->readOnly(),

                        // This is one of the FEW fields you are allowed to edit!
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

                    Section::make('Logistics & Fulfillment')
                        ->description('Immutable shipping destination and tracking details.')
                        ->icon('heroicon-o-truck')
                        ->schema([
                            
                            // Editable tracking fields for the warehouse team
                            Fieldset::make('Tracking Details')->schema([
                                TextInput::make('logistics_provider')
                                    ->label('Courier / Provider')
                                    ->placeholder('e.g., BlueDart, Delhivery'),
                                    
                                TextInput::make('tracking_number')
                                    ->label('Tracking Number')
                                    ->placeholder('e.g., AWB-123456789'),
                            ])->columns(2),

                            // The locked snapshot of where it goes
                            Fieldset::make('Shipping Destination (Snapshot)')->schema([
                                TextInput::make('shipping_name')->label('Recipient Name')->readOnly(),
                                TextInput::make('shipping_mobile')->label('Contact Mobile')->readOnly(),
                                TextInput::make('shipping_address_line_1')->label('Address Line 1')->columnSpanFull()->readOnly(),
                                TextInput::make('shipping_address_line_2')->label('Address Line 2')->columnSpanFull()->readOnly(),
                                TextInput::make('shipping_city')->label('City')->readOnly(),
                                TextInput::make('shipping_state')->label('State')->readOnly(),
                                TextInput::make('shipping_pincode')->label('Pincode')->readOnly(),
                            ])->columns(2),
                            
                        ]),
                ])->columnSpan(2),

                // ----------------------------------------------------------------
                // RIGHT COLUMN (Takes up 1/3 of the screen for financial data)
                // ----------------------------------------------------------------
                Grid::make(1)->schema([
                    
                    Section::make('Customer & Tenancy')->schema([
                        Select::make('company_id')
                            ->relationship('company', 'name')
                            ->disabled(),

                        Select::make('user_id')
                            ->relationship('user', 'email')
                            ->disabled(),
                    ]),

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
                    
                    Section::make('Billing & Tax Snapshot')
                        ->description('Immutable billing data used for the GST Invoice.')
                        ->icon('heroicon-o-document-text')
                        ->collapsed() // Kept collapsed to save space
                        ->schema([
                            KeyValue::make('billing_address_snapshot')
                                ->label('')
                                ->reorderable(false)
                                ->deletable(false)
                                ->addable(false)
                                ->editableKeys(false)
                                ->editableValues(false) // Locks the values from being changed
                        ]),
                        
                ])->columnSpan(1),

            ]),
        ]);
    }
}