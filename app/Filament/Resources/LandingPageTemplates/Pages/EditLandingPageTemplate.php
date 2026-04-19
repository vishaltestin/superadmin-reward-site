<?php

namespace App\Filament\Resources\LandingPageTemplates\Pages;

use App\Filament\Resources\LandingPageTemplates\LandingPageTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLandingPageTemplate extends EditRecord
{
    protected static string $resource = LandingPageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
