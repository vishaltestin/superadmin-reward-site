<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
// use App\Models\Transaction; // Swap with your actual Points model
use Illuminate\Support\Carbon;

class PlatformUsageChart extends ChartWidget
{
    protected ?string $heading = 'Points Distribution (Last 6 Months)';
    protected static ?int $sort = 3;
   protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $labels = [];
        $pointsData = [];

        // Loop dynamically through the last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $labels[] = $month->format('M Y'); // e.g., "Nov 2023"
            
            // REAL QUERY: Sum the points for this specific month
            // $pointsData[] = Transaction::whereMonth('created_at', $month->month)
            //                            ->whereYear('created_at', $month->year)
            //                            ->sum('points');
            
            $pointsData[] = rand(10000, 50000); // Placeholder until points table is linked
        }

        return [
            'datasets' => [
                [
                    'label' => 'Points Rewarded',
                    'data' => $pointsData,
                    'borderColor' => '#4f46e5', // Indigo
                    'backgroundColor' => 'rgba(79, 70, 229, 0.2)',
                    'fill' => true,
                    'tension' => 0.4, // Makes the line smoothly curved
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}