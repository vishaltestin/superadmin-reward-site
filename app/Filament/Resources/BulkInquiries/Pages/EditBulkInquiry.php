<?php

namespace App\Filament\Resources\BulkInquiries\Pages;

use App\Filament\Resources\BulkInquiries\BulkInquiryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBulkInquiry extends EditRecord
{
    protected static string $resource = BulkInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}