<?php

namespace App\Filament\Resources\Rewardees\Pages;

use App\Filament\Resources\Rewardees\RewardeeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRewardee extends EditRecord
{
    protected static string $resource = RewardeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
