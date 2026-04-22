<?php

namespace App\Filament\Resources\DemoRequests\Pages;

use App\Filament\Resources\DemoRequests\DemoRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDemoRequest extends CreateRecord
{
    protected static string $resource = DemoRequestResource::class;
}
