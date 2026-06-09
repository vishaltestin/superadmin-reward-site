<?php
namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {}

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Mail::extend('brevo', function () {
            return (new BrevoTransportFactory())->create(
                Dsn::fromString('brevo+api://' . config('services.brevo.key') . '@default')
            );
        });
    }
}
