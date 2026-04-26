<?php

namespace App\Filament\Resources\EventVariables\Pages;

use App\Filament\Resources\EventVariables\EventVariableResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEventVariable extends EditRecord
{
    protected static string $resource = EventVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
