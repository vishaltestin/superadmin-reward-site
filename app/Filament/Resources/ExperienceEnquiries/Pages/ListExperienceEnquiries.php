<?php

namespace App\Filament\Resources\ExperienceEnquiries\Pages;

use App\Filament\Resources\ExperienceEnquiries\ExperienceEnquiryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExperienceEnquiries extends ListRecords
{
    protected static string $resource = ExperienceEnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
