<?php

namespace App\Filament\Resources\BulkInquiries\Pages;

use App\Filament\Resources\BulkInquiries\BulkInquiryResource;
use Filament\Resources\Pages\ListRecords;

class ListBulkInquiries extends ListRecords
{
    protected static string $resource = BulkInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}