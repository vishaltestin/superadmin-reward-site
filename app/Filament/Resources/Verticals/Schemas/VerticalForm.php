<?php

namespace App\Filament\Resources\Verticals\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class VerticalForm
{
    public static function configure(Schema $schema) : Schema
    {
        return $schema->components([
            TextInput
                ::make('name')
                ->required()
                ->maxLength(255)
                ->live(debounce: 500)
                ->afterStateUpdated(
                    function (
                        Set $set,
                        ?string $state,
                        string $operation,
                    ) {
                    if ($operation === 'create') {
                        $set('slug', Str::slug($state));
                    }
                },
                ),

            TextInput::make('slug')
                ->required()
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn ($rule) => $rule->whereNull('deleted_at'),
                )
                ->readOnly(fn (string $operation) : bool => $operation === 'edit'),

            Textarea::make('description')->columnSpanFull(),

            Toggle::make('is_active')->default(true),
        ]);
    }
}