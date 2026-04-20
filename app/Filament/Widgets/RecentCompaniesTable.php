<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class RecentCompaniesTable extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Fetch the 5 most recently created companies
                Company::query()->latest()->limit(5)
            )
            ->heading('Recent Company Onboarding')
            ->columns([
                TextColumn::make('name')
                    ->weight('bold')
                    ->label('Company Name'),

                // Assuming you have a contact email or domain for the company
                TextColumn::make('email')
                    ->label('Admin Contact')
                    ->default('Pending Setup')
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active Status')
                    ->default(true),

                TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->label('Join Date')
                    ->badge()
                    ->color('success'),
            ])
            ->paginated(false);
    }
}