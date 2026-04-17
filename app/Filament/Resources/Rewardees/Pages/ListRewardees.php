<?php

namespace App\Filament\Resources\Rewardees\Pages;

use App\Filament\Resources\Rewardees\RewardeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRewardees extends ListRecords
{
    protected static string $resource = RewardeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
