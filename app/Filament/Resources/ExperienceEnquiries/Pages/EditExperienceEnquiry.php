<?php

namespace App\Filament\Resources\ExperienceEnquiries\Pages;

use App\Filament\Resources\ExperienceEnquiries\ExperienceEnquiryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditExperienceEnquiry extends EditRecord
{
    protected static string $resource = ExperienceEnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
