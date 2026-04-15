<?php

namespace App\Filament\Resources\Verticals\Pages;

use App\Filament\Resources\Verticals\VerticalResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditVertical extends EditRecord
{
    protected static string $resource = VerticalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
