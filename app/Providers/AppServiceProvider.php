<?php
namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        \Illuminate\Support\Facades\Mail::extend('smtp', function (array $config) {
            $transport = new \App\Mail\PatchedEsmtpTransport(
                $config['host'],
                $config['port'],
                false
            );

            if (isset($config['username'])) {
                $transport->setUsername($config['username']);
            }

            if (isset($config['password'])) {
                $transport->setPassword($config['password']);
            }

            return $transport;
        });
    }
}
