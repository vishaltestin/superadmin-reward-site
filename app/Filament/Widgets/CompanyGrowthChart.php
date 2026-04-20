<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class CompanyGrowthChart extends ChartWidget
{
    protected ?string $heading = 'New Company Onboarding';
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $labels = [];
        $companyData = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $labels[] = $month->format('M');
            
            // REAL QUERY: Count companies created in this month
            $companyData[] = Company::whereMonth('created_at', $month->month)
                                    ->whereYear('created_at', $month->year)
                                    ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'New Companies',
                    'data' => $companyData,
                    'backgroundColor' => '#10b981', // Emerald green
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}