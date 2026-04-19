<?php

namespace App\Filament\Resources\LandingPageTemplates\Pages;

use App\Filament\Resources\LandingPageTemplates\LandingPageTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLandingPageTemplates extends ListRecords
{
    protected static string $resource = LandingPageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
