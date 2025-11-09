<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Bridge\Mailtrap\Transport\MailtrapApiTransport;
use Psr\Log\LoggerInterface;

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
        Mail::extend('mailtrap+api', function () {
            $config = $this->app['config']->get('mail.mailers.mailtrap');

            if (empty($config['token']) || empty($config['inbox_id'])) {
                 throw new \Exception('Mailtrap token and inbox_id are required.');
            }

            return new MailtrapApiTransport(
                $config['token'],
                $config['inbox_id'],
                null,
                $this->app->make(LoggerInterface::class) // <-- Kita tetap kasih Logger
            );
        });
    }
}
