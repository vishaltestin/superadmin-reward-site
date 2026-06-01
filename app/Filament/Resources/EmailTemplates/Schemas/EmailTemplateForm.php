<?php
namespace App\Filament\Resources\EmailTemplates\Schemas;

use App\Models\Event;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([

                Section::make('Template Identification')->schema([
                    Grid::make(2)->schema([
                        Select::make('event_id')
                            ->label('Trigger Event')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->options(function () {
                                $events         = Event::with(['vertical', 'parent'])->get();
                                $groupedOptions = [];

                                foreach ($events as $event) {
                                    $isParent = $events->where('parent_id', $event->id)->count() > 0;
                                    if ($isParent) {
                                        continue;
                                    }

                                    $groupName = $event->vertical->name ?? 'Unassigned Vertical';
                                    if ($event->parent) {
                                        $groupName .= ' — ' . $event->parent->title;
                                    }

                                    $groupedOptions[$groupName][$event->id] = $event->title;
                                }

                                return $groupedOptions;
                            }),

                        Select::make('reward_type')
                            ->label('Template Layout Type (Reward)')
                            ->options([
                                'points' => 'Points Reward',
                                'code'   => 'Promo Code',
                                'link'   => 'Magic Link',
                            ])
                            ->placeholder('General / Master Template')
                            ->helperText('Leave blank for general emails, or select a specific type to restrict where this can be used.'),

                        Select::make('company_id')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Company (Leave blank for Global Master)'),

                        Toggle::make('is_active')
                            ->default(true)
                            ->inline(false)
                            ->label('Active Status'),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->required()
                            ->placeholder('e.g., Global Welcome Template'),

                        TextInput::make('subject')
                            ->required()
                            ->placeholder('e.g., Welcome to the team, {{ first_name }}!'),

                        FileUpload::make('thumbnail_path')
                            ->label('Template Thumbnail')
                            ->image()
                            ->disk('public')
                            ->directory('templates/thumbnails')
                            ->columnSpan(2)
                            ->helperText('This preview image will be visible in the Frontend Admin Portal.'),
                    ]),
                ]),

                Section::make('Email Code & Preview')->schema([
                    Grid::make(2)->schema([
                        CodeEditor::make('html_body')
                            ->label('HTML Source Code')
                            ->required()
                            ->live(debounce: 500)
                            ->columnSpan(1),

                        Placeholder::make('preview')
                            ->label('Live Visual Preview')
                            ->content(function ($get) {
                                $html = $get('html_body');

                                if (! $html) {
                                    return new HtmlString('<div style="padding: 20px; background: #f3f4f6; color: #9ca3af; text-align: center; border-radius: 8px;">Paste HTML to see preview...</div>');
                                }

                                return new HtmlString('<div style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; background: #fff; max-height: 500px; overflow-y: auto;">' . $html . '</div>');
                            })
                            ->columnSpan(1),
                    ]),
                ]),

            ]);
    }
}
