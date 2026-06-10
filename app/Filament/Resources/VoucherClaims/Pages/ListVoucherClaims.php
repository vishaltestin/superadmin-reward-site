<?php

namespace App\Filament\Resources\VoucherClaims\Pages;

use App\Filament\Resources\VoucherClaims\VoucherClaimResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVoucherClaims extends ListRecords
{
    protected static string $resource = VoucherClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
