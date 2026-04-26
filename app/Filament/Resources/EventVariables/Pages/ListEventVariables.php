<?php

namespace App\Filament\Resources\EventVariables\Pages;

use App\Filament\Resources\EventVariables\EventVariableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventVariables extends ListRecords
{
    protected static string $resource = EventVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
