<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\User;
// use App\Models\Transaction; // Swap with your actual Points model
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class GlobalStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // 1. Calculate dynamic sparklines (last 7 days)
        $companyTrend = [];
        $userTrend = [];
        $pointsTrend = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $companyTrend[] = Company::whereDate('created_at', '<=', $date)->count();
            $userTrend[] = User::whereNotNull('company_id')->whereDate('created_at', '<=', $date)->count();
            
            // Swap 'Transaction' and 'points' with your actual models
            // $pointsTrend[] = Transaction::whereDate('created_at', $date)->sum('points');
            $pointsTrend[] = rand(100, 1000); // Placeholder until you link your points table
        }

        return [
            Stat::make('Total Companies', Company::count())
                ->description('Active tenants on the platform')
                ->descriptionIcon('heroicon-m-building-office')
                ->chart($companyTrend)
                ->color('primary'),

            Stat::make('Total End Users', User::whereNotNull('company_id')->count())
                ->description('Employees & Clients')
                ->descriptionIcon('heroicon-m-users')
                ->chart($userTrend)
                ->color('success'),

            // Replace '1.2M' with Transaction::sum('points')
            Stat::make('Total Points Issued', '1.2M') 
                ->description('Total platform volume')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($pointsTrend)
                ->color('warning'),
        ];
    }
}