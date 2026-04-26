<?php
namespace App\Filament\Resources\EventVariables\Schemas;

use App\Models\Event;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EventVariableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('event_id')
                    ->label('Event (Leave blank for Global)')
                    ->searchable()
                    ->preload()
                    ->options(function () {
                        $events         = Event::with(['vertical', 'parent'])->get();
                        $groupedOptions = [];

                        foreach ($events as $event) {
                            // Skip parent categories — only show leaf/actionable events
                            $isParent = $events->where('parent_id', $event->id)->count() > 0;
                            if ($isParent) {
                                continue;
                            }

                            // Build group label: "Auto Dealers — Sales"
                            $groupName = $event->vertical->name ?? 'Unassigned Vertical';
                            if ($event->parent) {
                                $groupName .= ' — ' . $event->parent->title;
                            }

                            $groupedOptions[$groupName][$event->id] = $event->title;
                        }

                        return $groupedOptions;
                    })
                    ->default(null),

                // NEW FIELD: Usage Type
                Select::make('usage_type')
                    ->label('Where can this be used?')
                    ->options([
                        'both'         => 'Both (Email & Landing Page)',
                        'email'        => 'Email Only',
                        'landing_page' => 'Landing Page Only',
                    ])
                    ->default('both')
                    ->required(),

                TextInput::make('name')
                    ->required(),

                TextInput::make('value')
                    ->required(),

                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
