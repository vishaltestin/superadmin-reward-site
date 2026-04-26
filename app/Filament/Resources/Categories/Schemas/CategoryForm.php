<?php
namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs
                ::make('Category Setup')
                ->tabs([
                    Tab
                        ::make('Basic Details')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Grid
                                ::make(2)
                                ->schema([
                                    Select
                                        ::make('parent_id')
                                        ->relationship(
                                            name: 'parent',
                                            titleAttribute: 'name',
                                            modifyQueryUsing: fn(Builder $query, ?Category $record) => $record ? $query->where('id', '!=', $record->id) : $query,
                                        )
                                        ->searchable()
                                        ->preload()
                                        ->label('Parent Category (Leave blank if Top-Level)'),

                                    TextInput::make('name')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->live(debounce: 500)
                                        ->afterStateUpdated(
                                            function (
                                                Set $set,
                                                ?string $state,
                                                Get $get,
                                                string $operation,
                                            ) {
                                                if ($operation === 'create' && empty($get('slug'))) {
                                                    $set('slug', Str::slug($state));
                                                }
                                            },
                                        ),

                                    TextInput::make('slug')
                                        ->required()
                                        ->unique(
                                            ignoreRecord: true,
                                            modifyRuleUsing: fn($rule) => $rule->whereNull('deleted_at'),
                                        )
                                        ->readOnly(fn(string $operation): bool => $operation === 'edit'),

                                    Textarea::make('description')->default(null)->columnSpanFull(),

                                    TextInput::make('sort_order')->required()->numeric()->default(0),

                                    Toggle::make('is_active')->default(true),
                                ]),
                        ]),

                    Tab::make('Media & SEO')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            FileUpload
                                ::make('image')
                                ->image()
                                ->directory('categories')
                                ->columnSpanFull(),

                            TextInput::make('meta_title')->default(null),

                            TextInput::make('meta_keywords')->default(null),

                            Textarea::make('meta_description')->default(null)->columnSpanFull(),
                        ]),
                ])->columnSpanFull(),
        ]);
    }
}
