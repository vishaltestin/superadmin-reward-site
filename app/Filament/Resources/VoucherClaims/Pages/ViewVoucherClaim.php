<?php

namespace App\Filament\Resources\VoucherClaims\Pages;

use App\Filament\Resources\VoucherClaims\VoucherClaimResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVoucherClaim extends ViewRecord
{
    protected static string $resource = VoucherClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
