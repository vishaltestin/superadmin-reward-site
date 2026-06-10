<?php

namespace App\Filament\Resources\ExperienceEnquiries\Pages;

use App\Filament\Resources\ExperienceEnquiries\ExperienceEnquiryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewExperienceEnquiry extends ViewRecord
{
    protected static string $resource = ExperienceEnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
