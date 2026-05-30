<?php
namespace App\Filament\Resources\BulkInquiries\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BulkInquiryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(2)->schema([
                Grid::make(1)->schema([
                    Section::make('Inquiry Management')->schema([
                        Select::make('status')
                            ->options([
                                'inquiry_pending' => 'Pending (Awaiting Quote/Payment)',
                                'active'          => 'Active (Payment Received, Processing)',
                                'completed'       => 'Completed (Delivered to Client)',
                                'cancelled'       => 'Cancelled',
                            ])
                            ->required()
                            ->native(false),

                        Textarea::make('config_json.internal_admin_notes')
                            ->label('Internal Admin Notes')
                            ->placeholder('e.g. Wire transfer received on Tuesday. Dispatched via BlueDart.')
                            ->rows(3)
                            ->hint('Hidden from the client.'),
                    ]),

                    Section::make('Requested Product Matrix')
                        ->description('The exact breakdown of products, variants, and quantities the client needs.')
                        ->icon('heroicon-o-cube')
                        ->schema([
                            Repeater::make('config_json.bulk_items')
                                ->label('')
                                ->schema([
                                    TextInput::make('product_name')->readOnly()->columnSpan(2),
                                    TextInput::make('variant_name')->label('Variant')->readOnly()->columnSpan(2),
                                    TextInput::make('quantity')->numeric()->readOnly()->columnSpan(1),
                                ])
                                ->columns(5)
                                ->deletable(false)
                                ->addable(false)
                                ->reorderable(false),
                        ]),
                ]),

                Grid::make(1)->schema([
                    Section::make('Client Details')->schema([
                        Select::make('company_id')
                            ->relationship('company', 'name')
                            ->disabled()
                            ->label('Company'),

                        Select::make('created_by_user_id')
                            ->relationship('creator', 'email')
                            ->disabled()
                            ->label('Requested By'),
                    ]),

                    Section::make('Logistics Requirements')->schema([
                        TextInput::make('total_recipients')
                            ->label('Total Headcount')
                            ->readOnly(),

                        DatePicker::make('config_json.event_date')
                            ->label('Target Delivery Date')
                            ->readOnly(),

                        Textarea::make('config_json.event_address')
                            ->label('Delivery Address')
                            ->rows(4)
                            ->readOnly(),

                        Textarea::make('config_json.inquiry_notes')
                            ->label('Client Notes / Special Requests')
                            ->rows(4)
                            ->readOnly(),
                    ]),
                ]),
            ]),
        ]);
    }
}
