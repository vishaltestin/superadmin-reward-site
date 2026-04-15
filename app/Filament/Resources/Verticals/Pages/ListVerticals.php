<?php

namespace App\Filament\Resources\Verticals\Pages;

use App\Filament\Resources\Verticals\VerticalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVerticals extends ListRecords
{
    protected static string $resource = VerticalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
